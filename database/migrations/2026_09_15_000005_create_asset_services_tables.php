<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_services', function (Blueprint $table) {
            $table->id();
            $table->string('service_number', 50)->unique();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('provider_type', 20)->default('internal'); // internal, vendor
            $table->foreignId('serviced_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('technician_name', 150)->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->date('service_date');
            $table->date('completion_date')->nullable();
            $table->text('issue_description');
            $table->text('action_taken')->nullable();
            $table->string('before_service_photo')->nullable();
            $table->string('after_service_photo')->nullable();
            $table->string('receipt_document_path')->nullable();
            $table->unsignedBigInteger('total_cost')->default(0);
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asset_service_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_service_id')->constrained('asset_services')->cascadeOnDelete();
            $table->string('item_name');
            $table->integer('quantity')->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Setup permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view_any_asset::service',
            'view_asset::service',
            'create_asset::service',
            'update_asset::service',
            'delete_asset::service',
            'delete_any_asset::service',
            'export_asset::service',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        $rolesWithAccess = ['super_admin', 'admin', 'general_affair', 'audit'];
        foreach ($rolesWithAccess as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_service_items');
        Schema::dropIfExists('asset_services');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view_any_asset::service',
            'view_asset::service',
            'create_asset::service',
            'update_asset::service',
            'delete_asset::service',
            'delete_any_asset::service',
            'export_asset::service',
        ];

        Permission::whereIn('name', $permissions)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
