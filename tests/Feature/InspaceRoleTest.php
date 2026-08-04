<?php

namespace Tests\Feature;

use Statamic\Facades\Role;
use Tests\TestCase;

class InspaceRoleTest extends TestCase
{
    public function test_the_inspace_role_can_see_where_its_content_lands(): void
    {
        $role = Role::find('inspace');

        $this->assertNotNull($role, 'De koppelende partij heeft een CP-rol nodig om haar eigen content terug te vinden.');

        foreach (['access cp', 'view articles entries', 'edit articles entries'] as $permission) {
            $this->assertTrue($role->hasPermission($permission), "Rol mist `{$permission}`.");
        }
    }

    /**
     * Zonder rollen is `super` de enige manier waarop een gebruiker iets ziet,
     * en dat is precies de kortste weg naar te veel rechten. Deze test legt
     * vast dat de rol die weg niet alsnog inslaat.
     */
    public function test_the_inspace_role_is_not_a_super_user(): void
    {
        $role = Role::find('inspace');

        $this->assertFalse($role->isSuper(), 'De koppelende partij hoort geen content of gebruikers te kunnen verwijderen.');
        $this->assertFalse($role->hasPermission('configure collections'));
        $this->assertFalse($role->hasPermission('view users'));
    }
}
