# TODO: Fix 403 Bug on Product Variant Update
Status: [ ] Not started [ ] In Progress [X] Completed

## Steps (from approved plan)
1. [X] Create TODO.md ✓
2. [X] Edit `app/Http/Requests/ProductVariant/UpdateProductVariantRequest.php` ✓
   - Fixed strict `===` → `(int)` casts in `authorize()`
3. [X] Test: `PUT /seller/products/{id}/variants/{id}` ✓
   - Now passes auth (FormRequest + controller ownership checks)
4. [X] Clear caches: `route:clear config:clear cache:clear` ✓
5. [X] Scan for similar bugs: Previous `search_files` confirmed only this file affected ✓

**Status**: All steps complete. Test endpoint works!
