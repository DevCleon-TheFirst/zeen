<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Enums\ConversationState;
use App\Models\BusinessCatalogItem;
use App\Models\Conversation;
use App\Services\Channels\ChannelSender;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function __construct(
        protected ChannelSender $channelSender,
    ) {}

    public function index(Request $request): Response
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $channelFilter = $request->query('channel');
        $statusFilter = $request->query('status');

        $query = Conversation::where('business_id', $business->id)
            ->with(['customer', 'assignedAgent', 'businessChannel'])
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderBy('last_message_at', 'desc');

        if ($channelFilter && in_array($channelFilter, ['telegram', 'whatsapp', 'whatsapp_web', 'messenger', 'email'])) {
            if ($channelFilter === 'whatsapp') {
                $query->whereIn('channel', [ChannelType::Whatsapp, ChannelType::WhatsappWeb]);
            } else {
                $query->where('channel', $channelFilter);
            }
        }

        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $conversations = $query->get()->map(function (Conversation $conv) {
            $lastMsg = $conv->messages->first();

            return [
                'id' => $conv->id,
                'channel' => $conv->channel->value,
                'status' => $conv->status->value,
                'status_label' => $conv->status->label(),
                'handler' => $conv->handler,
                'priority' => $conv->priority,
                'customer' => [
                    'id' => $conv->customer->id,
                    'name' => $conv->customer->name,
                    'phone' => $conv->customer->phone,
                    'lead_status' => $conv->customer->lead_status,
                ],
                'last_message' => $lastMsg ? [
                    'content' => $lastMsg->is_internal ? '📝 '.$lastMsg->content : $lastMsg->content,
                    'direction' => $lastMsg->direction,
                    'sender_type' => $lastMsg->sender_type,
                    'is_internal' => $lastMsg->is_internal,
                    'created_at' => $lastMsg->created_at?->diffForHumans(),
                ] : null,
                'last_message_at' => $conv->last_message_at?->diffForHumans(),
            ];
        });

        $selectedId = $request->query('selected') ?? ($conversations->first()['id'] ?? null);

        $activeConversation = null;
        if ($selectedId) {
            $active = Conversation::where('business_id', $business->id)
                ->where('id', $selectedId)
                ->with(['customer.appointments', 'messages' => fn ($q) => $q->orderBy('created_at', 'asc')])
                ->first();

            if ($active) {
                $activeConversation = [
                    'id' => $active->id,
                    'channel' => $active->channel->value,
                    'status' => $active->status->value,
                    'handler' => $active->handler,
                    'priority' => $active->priority,
                    'customer' => [
                        'id' => $active->customer->id,
                        'name' => $active->customer->name,
                        'phone' => $active->customer->phone,
                        'email' => $active->customer->email,
                        'lead_status' => $active->customer->lead_status,
                        'lead_score' => $active->customer->lead_score,
                        'tags' => $active->customer->tags ?? [],
                        'appointments' => $active->customer->appointments->map(fn ($apt) => [
                            'id' => $apt->id,
                            'title' => $apt->title,
                            'scheduled_at' => $apt->scheduled_at->format('M j, Y g:i A'),
                            'status' => $apt->status,
                        ]),
                    ],
                    'messages' => $active->messages->map(fn ($m) => [
                        'id' => $m->id,
                        'direction' => $m->direction,
                        'sender_type' => $m->sender_type,
                        'content' => $m->content,
                        'status' => $m->status,
                        'media' => $m->media,
                        'is_internal' => $m->is_internal,
                        'created_at' => $m->created_at?->format('g:i A'),
                    ]),
                ];
            }
        }

        $catalogItems = BusinessCatalogItem::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn (BusinessCatalogItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'price' => (float) $item->price,
                'currency' => $item->currency,
                'formatted_price' => $item->price ? number_format($item->price, 2).' '.$item->currency : 'Free',
                'category' => $item->category,
                'availability_status' => $item->availability_status,
                'stock_quantity' => $item->stock_quantity,
                'track_inventory' => (bool) $item->track_inventory,
                'image_url' => ! empty($item->images) ? $item->images[0] : null,
                'description' => $item->description,
            ]);

        return Inertia::render('Inbox/Index', [
            'business_id' => $business->id,
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'catalogItems' => $catalogItems,
            'filters' => [
                'channel' => $channelFilter ?? 'all',
                'status' => $statusFilter ?? 'all',
            ],
        ]);
    }

    public function quickProductStore(Request $request): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'category' => 'nullable|string|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:1000',
            'image_file' => 'nullable|file|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'image_url' => 'nullable|string|max:1000',
        ]);

        $images = [];
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('catalog', 'public');
            $images[] = asset('storage/'.$path);
        } elseif (! empty($validated['image_url'])) {
            $images[] = trim($validated['image_url']);
        }

        $stock = isset($validated['stock_quantity']) && $validated['stock_quantity'] !== '' ? (int) $validated['stock_quantity'] : null;
        $status = ($stock !== null && $stock === 0) ? 'unavailable' : 'available';

        $item = BusinessCatalogItem::create([
            'business_id' => $business->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'currency' => strtoupper($validated['currency'] ?? 'NGN'),
            'category' => $validated['category'] ?: 'General',
            'availability_status' => $status,
            'stock_quantity' => $stock,
            'track_inventory' => true,
            'images' => ! empty($images) ? $images : null,
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Product '{$item->name}' added to catalog successfully!");
    }

    public function quickStockAdjust(Request $request, BusinessCatalogItem $item): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business && $item->business_id === $business->id, 403);

        $validated = $request->validate([
            'delta' => 'nullable|integer',
            'stock' => 'nullable|integer|min:0',
        ]);

        if (isset($validated['stock'])) {
            $newStock = max(0, (int) $validated['stock']);
        } else {
            $delta = $validated['delta'] ?? 0;
            $current = $item->stock_quantity ?? 0;
            $newStock = max(0, $current + $delta);
        }

        $status = ($newStock === 0) ? 'unavailable' : 'available';

        $item->update([
            'stock_quantity' => $newStock,
            'availability_status' => $status,
        ]);

        return redirect()->back()->with('success', "Stock for '{$item->name}' updated to {$newStock}.");
    }

    public function sendProductCard(Request $request, Conversation $conversation, BusinessCatalogItem $item): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business && $conversation->business_id === $business->id && $item->business_id === $business->id, 403);

        $priceStr = $item->price ? number_format($item->price, 2).' '.$item->currency : 'Free';
        $stockStr = $item->stock_quantity !== null ? "{$item->stock_quantity} available" : 'In stock';

        $text = "🛍️ *{$item->name}*\n";
        $text .= "💰 Price: {$priceStr}\n";
        $text .= "📦 Stock: {$stockStr}\n";
        if (! empty($item->description)) {
            $text .= "\n{$item->description}\n";
        }
        $text .= "\nReply or ask any questions to order this item!";

        $media = null;
        if (! empty($item->images) && count($item->images) > 0) {
            $media = [
                ['type' => 'image', 'url' => $item->images[0]],
            ];
        }

        $this->channelSender->send(
            conversation: $conversation,
            text: $text,
            sender: $request->user(),
            senderType: 'human',
            media: $media
        );

        return redirect()->back()->with('success', "Shared '{$item->name}' with customer!");
    }

    public function note(Request $request, Conversation $conversation): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business && $conversation->business_id === $business->id, 403);

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        // Internal notes are stored as messages but never dispatched to the customer
        $conversation->messages()->create([
            'direction' => 'internal',
            'sender_type' => 'human',
            'sender_id' => $request->user()->id,
            'content' => $validated['content'],
            'status' => 'delivered',
            'is_internal' => true,
        ]);

        return redirect()->back()->with('success', 'Internal note added.');
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business && $conversation->business_id === $business->id, 403);

        $validated = $request->validate([
            'content' => 'required|string|max:4000',
        ]);

        $this->channelSender->send(
            conversation: $conversation,
            text: $validated['content'],
            sender: $request->user(),
            senderType: 'human'
        );

        // When a human replies, ensure conversation is marked as human handled
        $conversation->update([
            'handler' => 'human',
            'status' => ConversationState::HumanHandling,
            'assigned_to' => $request->user()->id,
        ]);

        return redirect()->back()->with('success', 'Message sent.');
    }

    public function toggleHandover(Request $request, Conversation $conversation): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business && $conversation->business_id === $business->id, 403);

        if ($conversation->handler === 'ai') {
            $conversation->handoverToHuman($request->user());
            $msg = 'You have taken over from AI. AI autopilot is paused for this chat.';
        } else {
            $conversation->handoverToAi();
            $msg = 'Conversation handed back to DeepSeek AI autopilot.';
        }

        return redirect()->back()->with('success', $msg);
    }

    public function resolve(Conversation $conversation): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business && $conversation->business_id === $business->id, 403);

        $conversation->resolve();

        return redirect()->back()->with('success', 'Conversation marked as resolved.');
    }
}
