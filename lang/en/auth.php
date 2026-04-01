<?php

return [

    // ── Auth ──────────────────────────────────────────────────────────────────
    'auth' => [
        'login_success'           => 'Login successful',
        'logout_success'          => 'Logged out successfully',
        'invalid_credentials'     => 'Invalid credentials',
        'account_inactive'        => 'Your account is not active',
        'invalid_phone'           => 'Invalid Myanmar phone number format',
        'register_success'        => 'User registered successfully. Please verify your email.',
        'user_not_found'          => 'User not found',
        'user_not_authenticated'  => 'User not authenticated',
        'unauthorized'            => 'Unauthorized',
        'admin_required'          => 'Unauthorized. Admin access required.',
        'email_already_verified'  => 'Email already verified.',
        'verification_sent'       => 'Verification email resent.',
    ],

    // ── Orders ────────────────────────────────────────────────────────────────
    'orders' => [
        'placed_success'           => 'Order placed successfully',
        'not_found'                => 'Order not found',
        'otp_required'             => 'Order not verified. Please complete the email OTP step.',
        'otp_not_found'            => 'No OTP found. Please request a new code.',
        'otp_expired'              => 'This code has expired. Please request a new one.',
        'otp_incorrect'            => 'Incorrect code. Please try again.',
        'otp_verified'             => 'Code verified successfully.',
        'delivery_confirmed'       => 'Delivery confirmed successfully',
        'cancel_unauthorized'      => 'Unauthorized to cancel this order',
        'view_unauthorized'        => 'Unauthorized to view this order',
        'order_email_mismatch'     => 'The email address does not match this order.',
        'order_details_failed'     => 'Unable to retrieve order details. Please try again.',
        'insufficient_stock'       => 'Insufficient stock for ":name".',
        'product_unavailable'      => 'Product ":name" is no longer available.',
    ],

    // ── Products ──────────────────────────────────────────────────────────────
    'products' => [
        'created'                 => 'Product created successfully',
        'updated'                 => 'Product updated successfully',
        'deleted'                 => 'Product deleted successfully',
        'not_found'               => 'Product not found',
        'unauthorized_update'     => 'Unauthorized to update this product',
        'unauthorized_delete'     => 'Unauthorized to delete this product',
        'stock_limit'             => 'Cannot add more items. Only :count items available',
    ],

    // ── Cart ──────────────────────────────────────────────────────────────────
    'cart' => [
        'updated'     => 'Cart updated successfully',
        'cleared'     => 'Cart cleared successfully',
        'item_not_found' => 'Cart item not found',
    ],

    // ── Coupons ───────────────────────────────────────────────────────────────
    'coupons' => [
        'created'                  => 'Coupon created successfully',
        'updated'                  => 'Coupon updated successfully',
        'deleted'                  => 'Coupon deleted successfully',
        'applied'                  => 'Coupon applied successfully',
        'status_updated'           => 'Coupon status updated',
        'not_found'                => 'Coupon code not found',
        'invalid'                  => 'Invalid coupon code',
        'already_used'             => 'You have already used this coupon',
        'cart_empty'               => 'Cart is empty — no products to apply coupon to.',
        'min_order'                => 'Minimum order amount of :amount MMK required',
        'not_applicable'           => 'This coupon does not apply to any of your selected products',
    ],

    // ── Seller ────────────────────────────────────────────────────────────────
    'seller' => [
        'not_found'                  => 'Seller not found',
        'profile_not_found'          => 'Seller profile not found',
        'profile_not_created'        => 'Seller profile not created yet - start onboarding',
        'profile_initialised'        => 'Seller profile initialised',
        'profile_updated'            => 'Seller profile updated successfully',
        'profile_deleted'            => 'Seller profile deleted successfully',
        'profile_approved'           => 'Seller profile approved successfully',
        'profile_rejected'           => 'Seller profile rejected',
        'verification_rejected'      => 'Seller verification rejected',
        'verified'                   => 'Seller verified successfully',
        'status_updated'             => 'Seller status updated successfully',
        'verification_status_updated' => 'Verification status updated successfully',
        'not_a_seller'               => 'User is not a seller',
        'cannot_verify_incomplete'   => 'Cannot verify seller with incomplete profile',
        'cannot_verify_missing_docs' => 'Cannot verify seller with missing documents',
        'unauthorized_update'        => 'Unauthorized to update this seller profile',
        'followed'                   => 'Successfully followed seller',
        'unfollowed'                 => 'Successfully unfollowed seller',
        'already_following'          => 'Already following this seller',
        'cannot_follow_self'         => 'Cannot follow yourself',
        'unauthorized_followers'     => 'Unauthorized to view followers',
    ],

    // ── Onboarding ────────────────────────────────────────────────────────────
    'onboarding' => [
        'step_saved'              => ':step saved successfully',
        'submitted'               => 'Onboarding submitted successfully. Your store is now under review.',
        'completed'               => 'Seller onboarding completed successfully and submitted for approval',
        'redirect_to_onboarding'  => 'Seller profile not found. Redirect to onboarding.',
    ],

    // ── Store ─────────────────────────────────────────────────────────────────
    'store' => [
        'basic_updated'      => 'Store basic information updated successfully',
        'profile_updated'    => 'Store profile updated successfully',
        'policies_updated'   => 'Store policies updated successfully',
        'logo_updated'       => 'Store logo updated successfully',
        'logo_removed'       => 'Store logo removed successfully',
        'banner_updated'     => 'Store banner updated successfully',
        'banner_uploaded'    => 'Store banner uploaded successfully',
        'banner_removed'     => 'Store banner removed successfully',
    ],

    // ── Documents ─────────────────────────────────────────────────────────────
    'documents' => [
        'uploaded'              => 'Document uploaded successfully',
        'deleted'               => 'Document deleted successfully',
        'submitted'             => 'Documents submitted successfully for verification',
        'review_pending'        => 'Documents uploaded. Please review and submit for verification.',
        'required'              => 'Documents required. Please upload required documents.',
        'cannot_delete'         => 'Cannot delete documents after submission. Contact support for changes.',
        'some_invalid'          => 'Some uploaded documents are invalid or missing',
    ],

    // ── Business types ────────────────────────────────────────────────────────
    'business_types' => [
        'created'     => 'Business type created successfully',
        'updated'     => 'Business type updated successfully',
        'deleted'     => 'Business type deleted successfully',
        'not_found'   => 'Business type not found',
        'in_use'      => 'Cannot delete business type that is in use by sellers',
    ],

    // ── Business details ──────────────────────────────────────────────────────
    'business_details' => [
        'updated'       => 'Business details updated successfully',
        'hours_updated' => 'Business hours updated successfully',
        'not_found'     => 'Business type not found. Please complete store basic information first.',
    ],

    // ── Address ───────────────────────────────────────────────────────────────
    'address' => [
        'updated'   => 'Address information updated successfully',
    ],

    // ── Delivery ──────────────────────────────────────────────────────────────
    'delivery' => [
        'method_set'                => 'Delivery method set successfully',
        'status_updated'            => 'Status updated',
        'proof_uploaded'            => 'Proof uploaded successfully',
        'courier_assigned'          => 'Courier assigned successfully',
        'zones_saved'               => 'Delivery zones saved successfully',
        'area_created'              => 'Delivery area created successfully',
        'area_updated'              => 'Delivery area updated successfully',
        'area_deleted'              => 'Delivery area deleted successfully',
        'area_overlap'              => 'Delivery area overlaps with existing area: :area',
        'not_available'             => 'Delivery not available to this location',
        'not_available_zip'         => 'Shipping not available to this zip code',
        'not_available_location'    => 'Shipping not available to this location',
        'not_available_seller'      => 'Shipping not available from this seller',
        'unauthorized_update'       => 'Unauthorized to update this delivery area',
        'unauthorized_delete'       => 'Unauthorized to delete this delivery area',
        'shipping_enabled'          => 'Shipping enabled',
        'shipping_disabled'         => 'Shipping disabled',
        'settings_updated'          => 'Shipping settings updated successfully',
    ],

    // ── Reviews ───────────────────────────────────────────────────────────────
    'reviews' => [
        'unauthorized_delete'   => 'Unauthorized to delete this review',
        'unauthorized_update'   => 'Unauthorized to update this review',
        'already_reviewed_product' => 'You have already reviewed this product',
        'already_reviewed_seller'  => 'You have already reviewed this seller',
    ],

    // ── Discounts ─────────────────────────────────────────────────────────────
    'discounts' => [
        'created'               => 'Discount created successfully',
        'updated'               => 'Discount updated successfully',
        'deleted'               => 'Discount deleted successfully',
        'removed_from_product'  => 'Discount removed from product successfully',
        'unauthorized_apply'    => 'Unauthorized to apply discount to this product',
        'unauthorized_delete'   => 'Unauthorized to delete this discount',
        'unauthorized_remove'   => 'Unauthorized to remove discount from this product',
        'unauthorized_update'   => 'Unauthorized to update this discount',
    ],

    // ── Categories ────────────────────────────────────────────────────────────
    'categories' => [
        'created'           => 'Category created successfully.',
        'updated'           => 'Category updated successfully',
        'deleted'           => 'Category deleted successfully.',
        'has_products'      => 'Cannot delete category because it has associated products.',
    ],

    // ── Users (admin) ─────────────────────────────────────────────────────────
    'users' => [
        'created'           => 'User created successfully',
        'updated'           => 'User updated successfully',
        'deleted'           => 'User deleted successfully',
        'roles_assigned'    => 'Roles assigned successfully',
    ],

    // ── Commission rules ──────────────────────────────────────────────────────
    'commission' => [
        'rule_deleted'  => 'Rule deleted.',
    ],

    // ── Newsletter ────────────────────────────────────────────────────────────
    'newsletter' => [
        'already_subscribed'    => 'You are already subscribed!',
        'confirm_sent'          => 'Please check your email to confirm your subscription.',
        'confirmed'             => 'Subscription confirmed! Welcome to Pyonea updates.',
        'unsubscribed'          => 'You have been unsubscribed. You will no longer receive newsletters from Pyonea.',
        'preferences_updated'   => 'Preferences updated.',
    ],

    // ── Settings ──────────────────────────────────────────────────────────────
    'settings' => [
        'updated'   => 'Settings updated successfully',
    ],

    // ── General ───────────────────────────────────────────────────────────────
    'general' => [
        'validation_failed'     => 'Validation failed',
        'not_found'             => 'Not found',
        'server_error'          => 'An unexpected error occurred. Please try again.',
    ],

];
