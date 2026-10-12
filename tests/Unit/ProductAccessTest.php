<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\User;
use App\Support\ProductAccess;
use Mockery;
use PHPUnit\Framework\TestCase;

class ProductAccessTest extends TestCase
{
    public function test_seller_can_manage_only_their_own_product(): void
    {
        $seller = $this->userWithRoles(7, ['seller']);

        $this->assertTrue(ProductAccess::canManage($seller, new Product(['seller_id' => 7])));
        $this->assertFalse(ProductAccess::canManage($seller, new Product(['seller_id' => 8])));
    }

    public function test_admin_can_manage_products_and_buyer_seller_accounts_cannot(): void
    {
        $product = new Product(['seller_id' => 8]);
        $sellerOwnedProduct = new Product(['seller_id' => 7]);

        $this->assertTrue(ProductAccess::canManage($this->userWithRoles(7, ['admin']), $product));
        $this->assertFalse(
            ProductAccess::canManage($this->userWithRoles(7, ['buyer', 'seller']), $sellerOwnedProduct)
        );
    }

    public function test_only_sellers_without_buyer_or_admin_roles_can_create_products(): void
    {
        $this->assertTrue(ProductAccess::canCreate($this->userWithRoles(7, ['seller'])));
        $this->assertFalse(ProductAccess::canCreate($this->userWithRoles(7, ['buyer', 'seller'])));
        $this->assertFalse(ProductAccess::canCreate($this->userWithRoles(7, ['admin', 'seller'])));
    }

    private function userWithRoles(int $id, array $roles): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->setAttribute('id', $id);
        $user->shouldReceive('hasRole')
            ->with(Mockery::type('string'))
            ->andReturnUsing(fn (string $role): bool => in_array($role, $roles, true));

        return $user;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
