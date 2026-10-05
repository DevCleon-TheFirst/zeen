<?php

namespace Database\Seeders;

use App\Models\AutomationTemplate;
use Illuminate\Database\Seeder;

class AutomationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Real Estate: Lead Qualification & Inspection Booking',
                'industry' => 'real_estate',
                'description' => 'Automatically qualifies buyer/tenant leads, retrieves matching properties from catalog, and schedules physical inspections with agents.',
                'thumbnail' => '/images/templates/real_estate.png',
                'workflow_snapshot' => [
                    'nodes' => [
                        [
                            'id' => 'node_1',
                            'type' => 'trigger_whatsapp',
                            'label' => 'WhatsApp Property Inquiry',
                            'position' => ['x' => 100, 'y' => 150],
                            'config' => ['trigger' => 'new_message'],
                        ],
                        [
                            'id' => 'node_2',
                            'type' => 'ai_intent',
                            'label' => 'Classify Customer Intent',
                            'position' => ['x' => 350, 'y' => 150],
                            'config' => [
                                'intents' => [
                                    ['name' => 'inspection', 'description' => 'Customer wants to schedule an inspection'],
                                    ['name' => 'browse', 'description' => 'Customer wants to browse available listings'],
                                ],
                            ],
                        ],
                        [
                            'id' => 'node_3',
                            'type' => 'action_assign_staff',
                            'label' => 'Assign Agent & Send Booking',
                            'position' => ['x' => 650, 'y' => 80],
                            'config' => ['role' => 'agent'],
                        ],
                        [
                            'id' => 'node_4',
                            'type' => 'action_search_catalog',
                            'label' => 'Search Properties in Catalog',
                            'position' => ['x' => 650, 'y' => 240],
                            'config' => ['category' => 'property', 'limit' => 5],
                        ],
                        [
                            'id' => 'node_5',
                            'type' => 'action_send_message',
                            'label' => 'Send Matching Listings',
                            'position' => ['x' => 950, 'y' => 240],
                            'config' => ['message' => "Hello {{customer.name}}! Here are listings matching your inquiry:\n\n{{catalog.summary}}\n\nWould you like to book a physical inspection?"],
                        ],
                    ],
                    'edges' => [
                        ['id' => 'e1_2', 'source' => 'node_1', 'target' => 'node_2'],
                        ['id' => 'e2_3', 'source' => 'node_2', 'target' => 'node_3', 'label' => 'inspection'],
                        ['id' => 'e2_4', 'source' => 'node_2', 'target' => 'node_4', 'label' => 'browse'],
                        ['id' => 'e4_5', 'source' => 'node_4', 'target' => 'node_5'],
                    ],
                ],
                'is_published' => true,
            ],
            [
                'name' => 'General: Smart Human Escalation & Sentiment Alert',
                'industry' => 'general',
                'description' => 'Detects customer frustration, high-value inquiries, or explicit requests for a human, pauses AI immediately, and alerts on-duty staff.',
                'thumbnail' => '/images/templates/escalation.png',
                'workflow_snapshot' => [
                    'nodes' => [
                        [
                            'id' => 'node_1',
                            'type' => 'trigger_whatsapp',
                            'label' => 'Inbound Customer Message',
                            'position' => ['x' => 100, 'y' => 150],
                            'config' => ['trigger' => 'new_message'],
                        ],
                        [
                            'id' => 'node_2',
                            'type' => 'ai_intent',
                            'label' => 'Analyze Sentiment & Intent',
                            'position' => ['x' => 350, 'y' => 150],
                            'config' => [
                                'intents' => [
                                    ['name' => 'human_support', 'description' => 'Customer is frustrated or asks to speak with an agent'],
                                    ['name' => 'general', 'description' => 'Standard questions or automated inquiries'],
                                ],
                            ],
                        ],
                        [
                            'id' => 'node_3',
                            'type' => 'action_assign_staff',
                            'label' => 'Assign Staff & Alert Team',
                            'position' => ['x' => 650, 'y' => 80],
                            'config' => ['strategy' => 'round_robin'],
                        ],
                        [
                            'id' => 'node_4',
                            'type' => 'action_send_message',
                            'label' => 'Notify Customer of Handover',
                            'position' => ['x' => 950, 'y' => 80],
                            'config' => ['message' => 'Please hold on {{customer.name}}, I am connecting you with one of our support specialists right away.'],
                        ],
                        [
                            'id' => 'node_5',
                            'type' => 'action_send_message',
                            'label' => 'Continue AI Assistance',
                            'position' => ['x' => 650, 'y' => 240],
                            'config' => ['message' => 'Thanks for reaching out! How can we assist you today?'],
                        ],
                    ],
                    'edges' => [
                        ['id' => 'e1_2', 'source' => 'node_1', 'target' => 'node_2'],
                        ['id' => 'e2_3', 'source' => 'node_2', 'target' => 'node_3', 'label' => 'human_support'],
                        ['id' => 'e3_4', 'source' => 'node_3', 'target' => 'node_4'],
                        ['id' => 'e2_5', 'source' => 'node_2', 'target' => 'node_5', 'label' => 'general'],
                    ],
                ],
                'is_published' => true,
            ],
            [
                'name' => 'E-Commerce: AI Product Inquiry, Catalog Search & Order Follow-up',
                'industry' => 'retail',
                'description' => 'Presents catalog items, checks real-time inventory, provides instant product summaries, and automates checkout and order follow-up.',
                'thumbnail' => '/images/templates/ecommerce.png',
                'workflow_snapshot' => [
                    'nodes' => [
                        [
                            'id' => 'node_1',
                            'type' => 'trigger_order',
                            'label' => 'Order Completed / Paid',
                            'position' => ['x' => 100, 'y' => 150],
                            'config' => ['trigger' => 'order_created'],
                        ],
                        [
                            'id' => 'node_2',
                            'type' => 'action_add_tag',
                            'label' => 'Tag as Paying Customer',
                            'position' => ['x' => 350, 'y' => 150],
                            'config' => ['tag' => 'Buyer'],
                        ],
                        [
                            'id' => 'node_3',
                            'type' => 'action_send_message',
                            'label' => 'Send Order Confirmation',
                            'position' => ['x' => 650, 'y' => 150],
                            'config' => ['message' => 'Thank you {{customer.name}}! Your order {{order.tracking_code}} for {{order.currency}} {{order.total_amount}} has been confirmed.'],
                        ],
                        [
                            'id' => 'node_4',
                            'type' => 'delay',
                            'label' => 'Wait 24 Hours',
                            'position' => ['x' => 950, 'y' => 150],
                            'config' => ['delay_minutes' => 1440],
                        ],
                        [
                            'id' => 'node_5',
                            'type' => 'action_send_message',
                            'label' => 'Delivery Feedback Check-in',
                            'position' => ['x' => 1250, 'y' => 150],
                            'config' => ['message' => 'Hi {{customer.name}}, has order {{order.tracking_code}} arrived safely? Let us know if you need anything!'],
                        ],
                    ],
                    'edges' => [
                        ['id' => 'e1_2', 'source' => 'node_1', 'target' => 'node_2'],
                        ['id' => 'e2_3', 'source' => 'node_2', 'target' => 'node_3'],
                        ['id' => 'e3_4', 'source' => 'node_3', 'target' => 'node_4'],
                        ['id' => 'e4_5', 'source' => 'node_4', 'target' => 'node_5'],
                    ],
                ],
                'is_published' => true,
            ],
            [
                'name' => 'Services & Appointments: Consultation Booking & Payment Deposit',
                'industry' => 'services',
                'description' => 'Presents service tiers, books consultation sessions, and collects deposits via configured payment gateways.',
                'thumbnail' => '/images/templates/services.png',
                'workflow_snapshot' => [
                    'nodes' => [
                        [
                            'id' => 'node_1',
                            'type' => 'trigger_whatsapp',
                            'label' => 'Service Inquiry',
                            'position' => ['x' => 100, 'y' => 150],
                            'config' => ['trigger' => 'new_message'],
                        ],
                        [
                            'id' => 'node_2',
                            'type' => 'action_send_message',
                            'label' => 'Welcome & Service Details',
                            'position' => ['x' => 350, 'y' => 150],
                            'config' => ['message' => 'Welcome to our clinic {{customer.name}}! We offer consultation appointments Monday through Saturday.'],
                        ],
                        [
                            'id' => 'node_3',
                            'type' => 'action_assign_staff',
                            'label' => 'Assign Intake Specialist',
                            'position' => ['x' => 650, 'y' => 150],
                            'config' => ['strategy' => 'round_robin'],
                        ],
                    ],
                    'edges' => [
                        ['id' => 'e1_2', 'source' => 'node_1', 'target' => 'node_2'],
                        ['id' => 'e2_3', 'source' => 'node_2', 'target' => 'node_3'],
                    ],
                ],
                'is_published' => true,
            ],
        ];

        foreach ($templates as $t) {
            AutomationTemplate::updateOrCreate(['name' => $t['name']], $t);
        }
    }
}
