<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function seedActiveRoleCatalog(array $roles): void
    {
        if (! Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table) {
                $table->id();
                $table->string('nama_role');
                $table->string('is_active')->default('1');
                $table->timestamps();
            });
        }

        DB::table('role_user')->delete();
        foreach (array_unique($roles) as $role) {
            DB::table('role_user')->insert([
                'nama_role' => $role,
                'is_active' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
