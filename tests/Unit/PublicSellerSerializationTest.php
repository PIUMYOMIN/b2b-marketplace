<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\SellerController;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicSellerSerializationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('seller_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('identity_document_front')->nullable();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('seller_profiles');

        parent::tearDown();
    }

    public function test_public_seller_data_excludes_private_documents_and_loaded_relations(): void
    {
        $seller = new SellerProfile;
        $seller->forceFill([
            'id' => 12,
            'store_name' => 'Public Store',
            'store_slug' => 'public-store',
            'store_logo' => 'sellers/12/logo.png',
            'identity_document_front' => 'sellers/12/documents/id-front.png',
            'business_registration_document' => 'sellers/12/documents/registration.pdf',
            'additional_documents' => [
                ['path' => 'sellers/12/documents/private.pdf'],
            ],
            'account_number' => 'private-account',
            'nrc_number' => 'private-nrc',
        ]);
        $seller->setRelation('user', new User(['email' => 'private@example.test']));
        $seller->setRelation('reviews', collect([['review' => 'private relation']]));

        $publicData = (new PublicSellerSerializationController)->serializePublicSeller($seller);

        $this->assertSame('Public Store', $publicData['store_name']);
        $this->assertSame(url('storage/sellers/12/logo.png'), $publicData['store_logo']);
        $this->assertArrayNotHasKey('identity_document_front', $publicData);
        $this->assertArrayNotHasKey('business_registration_document', $publicData);
        $this->assertArrayNotHasKey('additional_documents', $publicData);
        $this->assertArrayNotHasKey('account_number', $publicData);
        $this->assertArrayNotHasKey('nrc_number', $publicData);
        $this->assertArrayHasKey('user', $publicData);
        $this->assertArrayNotHasKey('email', $publicData['user']);
        $this->assertArrayNotHasKey('reviews', $publicData);
    }

    public function test_document_urls_are_temporary_signed_links_without_file_paths(): void
    {
        $seller = new SellerProfile;
        $seller->forceFill([
            'id' => 12,
            'identity_document_front' => 'sellers/12/documents/id-front.png',
            'additional_documents' => [
                ['path' => 'sellers/12/documents/additional/private.pdf'],
            ],
        ]);

        $identityUrl = $seller->getDocumentUrl('identity_document_front');
        $additionalUrl = $seller->getDocumentUrl('sellers/12/documents/additional/private.pdf');

        $this->assertNotNull($identityUrl);
        $this->assertTrue(URL::hasValidSignature(Request::create($identityUrl)));
        $this->assertStringNotContainsString('id-front.png', $identityUrl);
        $this->assertNotNull($additionalUrl);
        $this->assertTrue(URL::hasValidSignature(Request::create($additionalUrl)));
        $this->assertStringNotContainsString('private.pdf', $additionalUrl);
    }

    public function test_signed_document_url_downloads_from_private_storage(): void
    {
        Storage::fake('local');
        $path = 'sellers/12/documents/identity_document_front/id-front.png';
        Storage::disk('local')->put($path, 'private document contents');

        DB::table('seller_profiles')->insert([
            'id' => 12,
            'identity_document_front' => $path,
        ]);
        $seller = SellerProfile::findOrFail(12);

        $this->get($seller->getDocumentUrl('identity_document_front'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_public_product_serialization_keeps_checkout_checks_before_seller_allowlisting(): void
    {
        $profile = new SellerProfile;
        $profile->forceFill([
            'id' => 12,
            'status' => SellerProfile::STATUS_ACTIVE,
            'store_name' => 'Public Store',
            'identity_document_front' => 'sellers/12/documents/private.png',
        ]);

        $seller = new User;
        $seller->forceFill(['id' => 34, 'name' => 'Public Seller']);
        $seller->setRelation('sellerProfile', $profile);

        $product = new Product;
        $product->forceFill([
            'id' => 56,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $product->setRelation('seller', $seller);

        $formatted = (new PublicSellerSerializationController)->serializeProduct($product);

        $this->assertTrue($formatted->getAttribute('can_checkout'));
        $this->assertNull($formatted->getAttribute('checkout_blocked_reason'));
        $this->assertSame('Public Seller', $formatted->getRelation('seller')['name']);
        $this->assertArrayNotHasKey(
            'identity_document_front',
            $formatted->getRelation('seller')['seller_profile']
        );
    }
}

final class PublicSellerSerializationController extends SellerController
{
    public function serializePublicSeller(SellerProfile $seller): array
    {
        return $this->formatPublicSeller($seller);
    }

    public function serializeProduct(Product $product): Product
    {
        return $this->formatPublicStorefrontProduct($product);
    }
}
