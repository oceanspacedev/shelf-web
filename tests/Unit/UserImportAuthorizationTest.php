<?php

namespace Tests\Unit;

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use App\Policies\UserPolicy;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Mockery;
use Tests\TestCase;

class UserImportAuthorizationTest extends TestCase
{
    public function test_import_is_allowed_for_user_creators_and_admins(): void
    {
        $policy = new UserPolicy;

        $this->assertTrue($policy->import($this->userThatCan(['create_user'])));
        $this->assertTrue($policy->import($this->userThatCan(['import_user'])));
        $this->assertTrue($policy->import($this->userWithRole('super_admin')));
        $this->assertTrue($policy->import($this->userWithRole('admin')));
        $this->assertFalse($policy->import($this->userThatCan([])));
    }

    public function test_users_list_exposes_excel_and_talenta_import_actions(): void
    {
        $page = new class extends ListUsers
        {
            public function headerActions(): array
            {
                return $this->getHeaderActions();
            }
        };

        $actions = collect($page->headerActions())->flatMap(function ($action) {
            return $action instanceof ActionGroup ? $action->getActions() : [$action];
        });

        $this->assertTrue($actions->contains(fn ($action) => $action instanceof ExcelImportAction));
        $this->assertTrue($actions->contains(
            fn ($action) => $action instanceof Action && $action->getName() === 'importTalenta',
        ));
    }

    /**
     * @param  list<string>  $abilities
     */
    private function userThatCan(array $abilities): User
    {
        $user = Mockery::mock(User::class)->shouldIgnoreMissing();
        $user->shouldReceive('can')->andReturnUsing(
            fn (string $ability): bool => in_array($ability, $abilities, true),
        );
        $user->shouldReceive('hasRole')->andReturn(false);

        return $user;
    }

    private function userWithRole(string $role): User
    {
        $user = Mockery::mock(User::class)->shouldIgnoreMissing();
        $user->shouldReceive('can')->andReturn(false);
        $user->shouldReceive('hasRole')->andReturnUsing(
            function ($roles) use ($role): bool {
                $roles = is_array($roles) ? $roles : [$roles];

                return in_array($role, $roles, true);
            },
        );

        return $user;
    }
}
