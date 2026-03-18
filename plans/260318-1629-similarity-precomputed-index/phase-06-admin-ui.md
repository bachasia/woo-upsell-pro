# Phase 06: Admin UI

## Context Links
- [plan.md](./plan.md)
- [Phase 05](./phase-05-hook-integration.md)
- [class-wup-settings-page.php](/Users/bachasia/Data/VibeCoding/woo-upsell-pro/admin/class-wup-settings-page.php) -- `render_batch_embed_card()`

## Overview
- **Priority**: P2
- **Status**: completed
- **Description**: Add "Similarity Index" card to AI Settings tab showing index status and rebuild button

## Key Insights
- Follow exact same UI pattern as existing `render_batch_embed_card()` (wup-card structure, inline JS, AJAX fetch)
- Reuse same nonce (`wup_batch_embed`) since it's already generated on this page
- Card positioned AFTER the "Product Embeddings" card since index depends on embeddings existing
- No new settings fields needed -- this is a status/action card only

## Requirements

### Functional
- Show: indexed count vs embedded count (e.g., "420 / 500 products indexed")
- Show: last full build timestamp (human-readable "2 hours ago" format)
- "Rebuild Similarity Index" button that triggers AJAX
- Button shows progress feedback: "Rebuild scheduled..." -> polls status
- Model mismatch indicator: if indexed model != active model, show warning

### Non-functional
- Consistent styling with existing cards
- No new CSS files (inline styles matching existing patterns)

## Architecture

### New Method: `render_similarity_index_card()`
Added to `WUP_Settings_Page`, called from `render()` after `render_batch_embed_card()`.

### UI Mockup
```
+--------------------------------------------------+
| Similarity Index                                  |
+--------------------------------------------------+
| Status          420 / 500 products indexed        |
|                 Last rebuilt: 2 hours ago          |
+--------------------------------------------------+
| Rebuild Index   [Rebuild Similarity Index]        |
|                 Rebuilds in background via chunks  |
|                 Rebuild scheduled. Refreshing...   |
+--------------------------------------------------+
```

## Related Code Files

### Files to Modify
- `admin/class-wup-settings-page.php` -- add `render_similarity_index_card()`, call from `render()`

## Implementation Steps

1. **Add card render call** in `render()`, after the batch embed card block:
   ```php
   <?php if ( $active_tab === 'wup-ai' ) : ?>
       <?php $this->render_batch_embed_card(); ?>
       <?php $this->render_similarity_index_card(); ?>
   <?php endif; ?>
   ```

2. **Implement `render_similarity_index_card()`**:
   ```php
   private function render_similarity_index_card(): void {
       $indexed_count  = WUP_Similarity_Index::count_indexed();
       $embedded_count = WUP_Plugin::get_instance()->count_embedded_products();
       $last_build     = (int) get_option( 'wup_similarity_last_full_build', 0 );
       $last_build_str = $last_build ? human_time_diff( $last_build ) . ' ago' : 'Never';
       $api_key        = get_option( 'wup_ai_api_key', '' );
       $nonce          = wp_create_nonce( 'wup_batch_embed' );
       ?>
       <div class="wup-card">
           <div class="wup-card-title">Similarity Index</div>
           <div class="wup-field">
               <div class="wup-field-label">
                   Status
                   <div class="wup-field-desc">Pre-computed product similarity lookup table</div>
               </div>
               <div class="wup-field-input">
                   <span id="wup-sim-indexed"><?php echo esc_html( $indexed_count ); ?></span>
                   / <?php echo esc_html( $embedded_count ); ?> products indexed
                   <br>
                   <small>Last rebuilt: <span id="wup-sim-last-build"><?php echo esc_html( $last_build_str ); ?></span></small>
               </div>
           </div>
           <?php if ( $api_key && $embedded_count > 0 ) : ?>
           <div class="wup-field">
               <div class="wup-field-label">
                   Rebuild Index
                   <div class="wup-field-desc">Recompute similarity rankings for all embedded products. Runs in background.</div>
               </div>
               <div class="wup-field-input">
                   <button type="button" id="wup-rebuild-sim-btn" class="wup-btn-secondary">
                       Rebuild Similarity Index
                   </button>
                   <span id="wup-rebuild-sim-status" style="font-size:12px;color:#10b981;margin-left:8px;"></span>
               </div>
           </div>
           <script>
           (function(){
               var btn    = document.getElementById('wup-rebuild-sim-btn');
               var status = document.getElementById('wup-rebuild-sim-status');
               if (!btn) return;

               btn.addEventListener('click', function(){
                   btn.disabled = true;
                   status.textContent = 'Scheduling...';
                   fetch(ajaxurl, {
                       method: 'POST',
                       headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                       body: new URLSearchParams({
                           action: 'wup_rebuild_similarity',
                           nonce: '<?php echo esc_js( $nonce ); ?>'
                       })
                   })
                   .then(function(r){ return r.json(); })
                   .then(function(d){
                       if (d.success) {
                           status.textContent = 'Rebuild scheduled. Progress updates on page reload.';
                           // Poll status after 10s
                           setTimeout(pollStatus, 10000);
                       } else {
                           status.textContent = 'Error: ' + (d.data || 'unknown');
                       }
                       btn.disabled = false;
                   })
                   .catch(function(e){
                       status.textContent = 'Network error';
                       btn.disabled = false;
                   });
               });

               function pollStatus() {
                   fetch(ajaxurl, {
                       method: 'POST',
                       headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                       body: new URLSearchParams({
                           action: 'wup_similarity_status',
                           nonce: '<?php echo esc_js( $nonce ); ?>'
                       })
                   })
                   .then(function(r){ return r.json(); })
                   .then(function(d){
                       if (d.success) {
                           var el = document.getElementById('wup-sim-indexed');
                           if (el) el.textContent = d.data.indexed;
                           status.textContent = d.data.indexed + ' indexed';
                       }
                   });
               }
           })();
           </script>
           <?php elseif ( ! $api_key ) : ?>
           <div class="wup-field">
               <div class="wup-field-label">
                   <div class="wup-field-desc" style="color:#f59e0b;">&#9888; Configure API key and generate embeddings first.</div>
               </div>
           </div>
           <?php else : ?>
           <div class="wup-field">
               <div class="wup-field-label">
                   <div class="wup-field-desc" style="color:#f59e0b;">&#9888; Generate product embeddings first (above), then rebuild the similarity index.</div>
               </div>
           </div>
           <?php endif; ?>
       </div>
       <?php
   }
   ```

3. **Make `count_embedded_products` accessible**: Currently private in `WUP_Plugin`. Either:
   - Make it `public` (simple, follows existing patterns)
   - Or call the same SQL inline in the card render (duplicates 3 lines)

   Recommended: change `private function count_embedded_products()` to `public static function count_embedded_products()` in `WUP_Plugin`. It's a read-only counter, safe to expose.

   Alternative: use `WUP_Similarity_Batch::count_embedded_products()` which we added in Phase 03.

## Todo List
- [x] Add `render_similarity_index_card()` method to `WUP_Settings_Page`
- [x] Call it in `render()` after batch embed card
- [x] Ensure `count_embedded_products` is accessible (use batch class method)
- [x] Test: card renders with correct counts
- [x] Test: rebuild button triggers AJAX and shows feedback
- [x] Test: card shows warning when no API key
- [x] Test: card shows warning when no embeddings exist
- [x] Verify settings page file stays under 200 lines (currently 437 lines -- already over limit, but this is an existing issue. Add only the new method, do NOT refactor the whole file in this phase)

## Success Criteria
- Similarity Index card visible on AI Settings tab
- Shows correct indexed/embedded counts
- Rebuild button triggers background job
- Status updates after poll
- Graceful messaging when prerequisites not met

## Risk Assessment
- **File size**: `class-wup-settings-page.php` is already 437 lines. Adding ~70 more pushes to ~507. This exceeds the 200-line guideline but the file already does. Consider extracting the batch embed card and similarity card into a separate partial/trait in a future cleanup. Not in scope for this task.
- **Nonce expiry**: nonce is generated on page load. If admin leaves page open >24h, AJAX will fail with nonce error. Standard WP behavior, acceptable.

## Security Considerations
- Reuses existing nonce + capability pattern
- No new user inputs
- Button triggers background job, does not perform computation inline

## Next Steps
- After all phases: integration testing, docs update
