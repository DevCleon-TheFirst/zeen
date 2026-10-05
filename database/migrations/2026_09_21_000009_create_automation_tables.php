<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Workflow definitions
        Schema::create('automation_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_type');
            // new_message|new_customer|payment_received|appointment_created|
            // lead_status_changed|customer_inactive|form_submitted|scheduled
            $table->json('trigger_config')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamps();

            $table->index(['business_id', 'trigger_type', 'is_active']);
        });

        // Nodes on the canvas
        Schema::create('automation_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('automation_workflows')->cascadeOnDelete();
            $table->string('node_id'); // UUID from Vue Flow
            $table->string('type'); // trigger|ai_step|condition|action|delay|end
            $table->string('label')->nullable();
            $table->json('config')->nullable(); // tool name, message text, condition, etc.
            $table->decimal('position_x', 10, 2)->default(0);
            $table->decimal('position_y', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['workflow_id', 'node_id']);
            $table->index('workflow_id');
        });

        // Directed edges between nodes
        Schema::create('automation_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('automation_workflows')->cascadeOnDelete();
            $table->string('edge_id'); // UUID from Vue Flow
            $table->string('source_node_id');
            $table->string('target_node_id');
            $table->string('condition_label')->nullable(); // null|'yes'|'no'|'property'|etc.
            $table->timestamps();

            $table->unique(['workflow_id', 'edge_id']);
            $table->index('workflow_id');
        });

        // Execution logs per trigger
        Schema::create('automation_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('automation_workflows')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('status')->default('running'); // running|completed|failed|cancelled
            $table->string('current_node_id')->nullable();
            $table->json('context')->nullable(); // runtime variables
            $table->json('execution_log')->nullable(); // array of step results
            $table->string('error_message')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'status']);
            $table->index(['conversation_id']);
        });

        // Pre-built industry templates
        Schema::create('automation_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('industry'); // real_estate|hotel|clinic|retail|school|church|service
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->json('workflow_snapshot'); // full nodes + edges
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_templates');
        Schema::dropIfExists('automation_executions');
        Schema::dropIfExists('automation_edges');
        Schema::dropIfExists('automation_nodes');
        Schema::dropIfExists('automation_workflows');
    }
};
