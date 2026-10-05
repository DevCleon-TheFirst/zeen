<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request): Response
    {
        $business = TenantContext::get();
        abort_unless($business, 404);

        $status = $request->query('status');

        $query = Appointment::where('business_id', $business->id)
            ->with(['customer', 'assignedStaff', 'conversation'])
            ->orderBy('scheduled_at', 'asc');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $appointments = $query->paginate(20)->withQueryString()->through(fn (Appointment $apt) => [
            'id' => $apt->id,
            'title' => $apt->title,
            'description' => $apt->description,
            'location' => $apt->location,
            'scheduled_at' => $apt->scheduled_at->format('M j, Y g:i A'),
            'scheduled_date' => $apt->scheduled_at->format('Y-m-d'),
            'scheduled_time' => $apt->scheduled_at->format('H:i'),
            'duration_minutes' => $apt->duration_minutes,
            'status' => $apt->status,
            'customer' => [
                'id' => $apt->customer->id,
                'name' => $apt->customer->name,
                'phone' => $apt->customer->phone,
            ],
            'conversation_id' => $apt->conversation_id,
            'assigned_staff' => $apt->assignedStaff?->name,
            'created_at' => $apt->created_at?->diffForHumans(),
        ]);

        return Inertia::render('Appointments/Index', [
            'appointments' => $appointments,
            'filters' => [
                'status' => $status ?? 'all',
            ],
        ]);
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business && $appointment->business_id === $business->id, 403);

        $validated = $request->validate([
            'status' => 'required|string|in:pending,confirmed,completed,cancelled,no_show',
            'location' => 'nullable|string|max:255',
            'cancellation_reason' => 'nullable|string|max:1000',
        ]);

        $appointment->update($validated);

        return redirect()->back()->with('success', 'Appointment status updated.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $business = TenantContext::get();
        abort_unless($business && $appointment->business_id === $business->id, 403);

        $appointment->delete();

        return redirect()->back()->with('success', 'Appointment removed.');
    }
}
