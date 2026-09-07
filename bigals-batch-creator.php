<?php
/**
 * Plugin Name: Big Al's Batch Product Creator
 * Description: Redesign & Custom High-Speed Batch Product Workflow for WooCommerce.
 * Version: 1.1.0
 * Author: Big Al's Dev Team
 * Text Domain: bigals-batch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BigAls_Batch_Creator {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	public function register_admin_menu() {
		add_menu_page(
			'Batch Creator',
			'Batch Creator',
			'manage_woocommerce',
			'bigals-batch-creator',
			array( $this, 'render_admin_page' ),
			'dashicons-superhero',
			56
		);
		add_submenu_page(
			'bigals-batch-creator',
			'Attribute Presets',
			'Attribute Presets',
			'manage_woocommerce',
			'bigals-attribute-presets',
			array( $this, 'render_attribute_presets_page' )
		);
		add_submenu_page(
			'bigals-batch-creator',
			'AI Settings',
			'AI Settings',
			'manage_woocommerce',
			'bigals-ai-settings',
			array( $this, 'render_ai_settings_page' )
		);
	}

	public function render_ai_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage AI settings.', 'bigals-batch' ) );
		}
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			check_admin_referer( 'bigals_save_ai_settings' );
			update_option( 'bigals_gemini_api_key', sanitize_text_field( wp_unslash( $_POST['gemini_api_key'] ?? '' ) ), false );
			update_option( 'bigals_gemini_model', sanitize_text_field( wp_unslash( $_POST['gemini_model'] ?? 'gemini-3.6-flash' ) ), false );
		}
		$ai_monitor = get_option( 'bigals_gemini_monitor', array() );
		$ai_monitor = is_array( $ai_monitor ) ? $ai_monitor : array();
		?>
		<div class="wrap">
			<h1>AI Product Copy Settings</h1>
			<?php if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) : ?><div class="notice notice-success is-dismissible"><p>AI settings saved.</p></div><?php endif; ?>
			<p>Gemini API key yahan save karein. Key browser ya product payload mein expose nahi hoti. Key Google AI Studio se banayein: <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer">Get Gemini API key</a>.</p>
			<form method="post" style="max-width:760px;background:#fff;border:1px solid #ccd0d4;padding:20px;margin-top:20px;">
				<?php wp_nonce_field( 'bigals_save_ai_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="gemini_api_key">Gemini API Key</label></th><td><input class="regular-text" type="password" id="gemini_api_key" name="gemini_api_key" value="<?php echo esc_attr( get_option( 'bigals_gemini_api_key', '' ) ); ?>" autocomplete="new-password"><p class="description">Google AI Studio se API key milegi.</p></td></tr>
					<tr><th><label for="gemini_model">Gemini Model</label></th><td><input class="regular-text" id="gemini_model" name="gemini_model" value="<?php echo esc_attr( get_option( 'bigals_gemini_model', 'gemini-3.6-flash' ) ); ?>"><p class="description">Default: gemini-3.6-flash</p></td></tr>
				</table>
				<?php submit_button( 'Save AI Settings' ); ?>
				<button type="button" class="button" id="bigalsTestGemini">Test API Connection</button>
				<span id="bigalsGeminiTestResult" style="margin-left:10px;"></span>
			</form>
			<p><strong>Last check:</strong> <?php echo ! empty( $ai_monitor['checked_at'] ) ? esc_html( $ai_monitor['checked_at'] ) : 'Not checked yet'; ?><br>
			<strong>Status:</strong> <?php echo ! empty( $ai_monitor['message'] ) ? esc_html( $ai_monitor['message'] ) : 'No result yet'; ?></p>
			<script>
			document.getElementById('bigalsTestGemini').addEventListener('click', function () {
				let button = this;
				let result = document.getElementById('bigalsGeminiTestResult');
				button.disabled = true;
				result.textContent = 'Checking...';
				fetch('<?php echo esc_url_raw( rest_url( 'bigals/v1/test-gemini/' ) ); ?>', {
					method: 'POST',
					headers: { 'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>' }
				})
				.then(response => response.json().then(data => ({ ok: response.ok, data: data })))
				.then(response => { result.textContent = response.data.message || 'Test completed.'; result.style.color = response.ok ? 'green' : 'red'; })
				.catch(error => { result.textContent = error.message; result.style.color = 'red'; })
				.finally(() => { button.disabled = false; });
			});
		</script>
		</div>
		<?php
	}

	public function render_attribute_presets_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage attribute presets.', 'bigals-batch' ) );
		}

		$message = '';
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			check_admin_referer( 'bigals_manage_attribute_presets' );
			$presets = $this->get_attribute_presets();
			$action = sanitize_key( $_POST['preset_action'] ?? '' );
			if ( 'save' === $action ) {
				$preset = $this->sanitize_attribute_preset( wp_unslash( $_POST ) );
				if ( $preset['name'] && ( $preset['colors'] || $preset['sizes'] ) ) {
					$presets[ $preset['id'] ] = $preset;
					update_option( 'bigals_attribute_presets', $presets, false );
					$message = 'Preset saved successfully.';
				} else {
					$message = 'Enter a preset name and at least one color or size.';
				}
			} elseif ( 'delete' === $action ) {
				$id = sanitize_key( $_POST['preset_id'] ?? '' );
				if ( isset( $presets[ $id ] ) ) {
					unset( $presets[ $id ] );
					update_option( 'bigals_attribute_presets', $presets, false );
					$message = 'Preset deleted successfully.';
				}
			}
		}

		$presets = $this->get_attribute_presets();
		?>
		<div class="wrap">
			<h1>Attribute Preset Manager</h1>
			<?php if ( $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>
			<p>Save frequently used colors and sizes here. Apply them later from the Batch Creator page.</p>
			<form method="post" style="max-width:760px;background:#fff;border:1px solid #ccd0d4;padding:20px;margin-top:20px;">
				<?php wp_nonce_field( 'bigals_manage_attribute_presets' ); ?>
				<input type="hidden" name="preset_action" value="save">
				<table class="form-table" role="presentation">
					<tr><th><label for="preset_name">Preset Name</label></th><td><input class="regular-text" id="preset_name" name="name" required placeholder="Apparel"></td></tr>
					<tr><th><label for="preset_colors">Colors</label></th><td><input class="regular-text" id="preset_colors" name="colors" placeholder="Red, Blue, Green"><p class="description">Comma-separated values.</p></td></tr>
					<tr><th><label for="preset_sizes">Sizes</label></th><td><input class="regular-text" id="preset_sizes" name="sizes" placeholder="S, M, L, XL"><p class="description">Comma-separated values.</p></td></tr>
				</table>
				<?php submit_button( 'Save Preset' ); ?>
			</form>
			<h2>Saved Presets</h2>
			<table class="widefat striped" style="max-width:900px;">
				<thead><tr><th>Name</th><th>Colors</th><th>Sizes</th><th>Action</th></tr></thead>
				<tbody>
				<?php if ( ! $presets ) : ?><tr><td colspan="4">No presets saved yet.</td></tr><?php endif; ?>
				<?php foreach ( $presets as $preset ) : ?>
					<tr>
						<td><?php echo esc_html( $preset['name'] ); ?></td>
						<td><?php echo esc_html( implode( ', ', $preset['colors'] ) ); ?></td>
						<td><?php echo esc_html( implode( ', ', $preset['sizes'] ) ); ?></td>
						<td>
							<form method="post">
								<?php wp_nonce_field( 'bigals_manage_attribute_presets' ); ?>
								<input type="hidden" name="preset_action" value="delete">
								<input type="hidden" name="preset_id" value="<?php echo esc_attr( $preset['id'] ); ?>">
								<button type="submit" class="button" onclick="return confirm('Delete this preset?');">Delete</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_bigals-batch-creator' !== $hook ) {
			return;
		}
		wp_enqueue_media();
	}

	public function render_admin_page() {
		?>
		<div class="wrap bigals-wrap">
			<h1 class="wp-heading-inline">Big Al's High-Speed Batch Product Creator</h1>
			<hr class="wp-header-end">

			<style>
				.bigals-wrap { --panel: #1d2026; --panel-deep: #15181d; --line: #343943; --muted: #9aa3b2; --accent: #ff6b1a; background: radial-gradient(circle at 85% 0%, #252a33 0, #121417 42%, #0d0f12 100%); color: #e8ebf0; padding: 28px; border: 1px solid #2d323b; border-radius: 12px; margin-top: 15px; box-shadow: 0 18px 45px rgba(0,0,0,.28); }
				.bigals-wrap > h1 { color: #fff; letter-spacing: .2px; font-size: 24px; margin-bottom: 6px; }
				.bigals-card { background: rgba(29,32,38,.92); border: 1px solid var(--line); padding: 22px; border-radius: 10px; margin-bottom: 22px; box-shadow: 0 8px 22px rgba(0,0,0,.16); }
				.bigals-card h2 { color: #fff; margin-top: 0; font-size: 17px; border-bottom: 1px solid var(--line); padding-bottom: 13px; letter-spacing: .1px; }
				.photo-strip { display: flex; gap: 8px; overflow-x: auto; padding: 18px 8px; background: #0d0f12; border: 1px solid #252a31; border-radius: 8px; align-items: center; scrollbar-color: #596170 #171a20; }
				.photo-item { min-width: 112px; height: 112px; background: #252a31; border: 2px solid #414854; border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; user-select:none; overflow:hidden; }
				
				/* Interactive Divider Bar */
				.divider-bar { width: 18px; height: 100px; display: flex; align-items: center; justify-content: center; cursor: pointer; }
				.divider-line { width: 4px; height: 80%; background: #333; border-radius: 2px; transition: 0.2s; }
				.divider-bar:hover .divider-line { background: #ff6b00; height: 100%; }
				.divider-bar.active .divider-line { background: #ff6b00; height: 100%; box-shadow: 0 0 8px #ff6b00; }

				.item-badge { position: absolute; top: 4px; left: 4px; background: #ff6b00; color: #000; font-weight: bold; font-size: 10px; padding: 2px 5px; border-radius: 3px; z-index:2; }
				.img-role-badge { position: absolute; bottom: 4px; right: 4px; background: rgba(0,0,0,0.8); color: #fff; font-size: 9px; padding: 1px 4px; border-radius: 2px; }
				
				.range-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; background: var(--panel-deep); border: 1px solid #252a31; padding: 18px; border-radius: 8px; align-items: end; }
				.preset-grid { grid-template-columns: minmax(260px, 360px) minmax(210px, 280px); justify-content: space-between; }
				.preset-grid .btn-accent { width: 100%; }
				.form-group { display: flex; flex-direction: column; gap: 5px; }
				.form-group label { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .7px; font-weight: 600; }
				.range-grid input, .range-grid select, .range-grid textarea { background: #20242b; border: 1px solid #424955; color: #fff; padding: 10px; border-radius: 6px; min-height: 40px; box-sizing: border-box; }
				.range-grid input:focus, .range-grid select:focus, .range-grid textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(255,107,26,.18); outline: none; }
				.btn-accent { background: linear-gradient(135deg, #ff7a26, #ed5710); color: #111; font-weight: 700; border: none; padding: 11px 17px; border-radius: 6px; cursor: pointer; box-shadow: 0 5px 14px rgba(255,107,26,.2); }
				.btn-accent:hover { filter: brightness(1.08); transform: translateY(-1px); }
				.grid-scroll { width: 100%; overflow-x: auto; overflow-y: hidden; margin-top: 18px; border: 1px solid #303640; border-radius: 8px; background: #14171b; scrollbar-color: #596170 #171a20; }
				.grid-table { width: max-content; min-width: 1850px; border-collapse: separate; border-spacing: 0; font-size: 12px; }
				.grid-table th, .grid-table td { padding: 13px 14px; border-bottom: 1px solid #2d323a; text-align: left; vertical-align: middle; white-space: nowrap; }
				.grid-table th { position: sticky; top: 0; z-index: 2; background: #171a1f; color: #9fa8b7; text-transform: uppercase; font-size: 10px; letter-spacing: .6px; }
				.grid-table tbody tr { background: #1d2026; transition: background .15s ease; }
				.grid-table tbody tr:nth-child(even) { background: #20242a; }
				.grid-table tbody tr:hover { background: #292f38; }
				.grid-table td code { color: #cbd2dc; background: #101216; padding: 4px 6px; border-radius: 4px; }
				.grid-table .col-title { min-width: 240px; max-width: 300px; white-space: normal; line-height: 1.45; font-weight: 600; color: #f3f5f7; }
				.grid-table .col-short-description, .grid-table .col-description { min-width: 300px; max-width: 390px; white-space: normal; line-height: 1.5; color: #c0c7d1; }
				.grid-table .tag { display: inline-block; }
				.grid-table .button { border-color: #4c57d8; background: #20243c; color: #b9c0ff; }
				.grid-table .button:hover { background: #343d75; color: #fff; }
				@media (max-width: 782px) { .bigals-wrap { padding: 15px; margin-right: 10px; } .bigals-card { padding: 15px; } .bigals-wrap > h1 { font-size: 20px; } .preset-grid { grid-template-columns: 1fr; } }
				.tag-group { display: flex; gap: 4px; flex-wrap: wrap; }
				.tag { background: #2d3748; color: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
				.tag-color { background: #1a365d; color: #90cdf4; }
				.tag-size { background: #276749; color: #9ae6b4; }
				.variation-drawer-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.65); z-index:100000; }
				.variation-drawer { position:absolute; top:0; right:0; width:min(440px, 100%); height:100%; box-sizing:border-box; background:#1e1e1e; border-left:1px solid #444; padding:24px; overflow-y:auto; box-shadow:-8px 0 24px rgba(0,0,0,.4); }
				.variation-drawer h2 { margin-top:0; color:#fff; }
				.variation-drawer label { display:block; margin:18px 0 6px; color:#bbb; font-size:12px; text-transform:uppercase; }
				.variation-drawer input, .variation-drawer select, .variation-drawer textarea { width:100%; box-sizing:border-box; background:#222; color:#fff; border:1px solid #555; border-radius:4px; padding:9px; }
				.variation-rule-row { display:grid; grid-template-columns:1fr 1fr 90px auto; gap:6px; align-items:end; }
				.variation-rule-list { margin-top:10px; display:grid; gap:6px; }
				.variation-rule-item { display:flex; justify-content:space-between; gap:8px; align-items:center; background:#292929; padding:7px; font-size:12px; }
				.variation-matrix { margin-top:14px; padding:10px; background:#151515; border:1px solid #333; font-size:12px; white-space:pre-line; }
				.variation-drawer-actions { display:flex; gap:8px; margin-top:22px; }
				.btn-secondary { background:#444; color:#fff; border:0; padding:10px 15px; border-radius:4px; cursor:pointer; }
                .wrap h1.wp-heading-inline {
    color: #fff;
}

.wp-core-ui select:hover {
    color: #fff;
   
}

.range-grid select option {
    color: #fff;
}
			</style>

			<!-- 1. Media Upload & Divider Engine -->
			<div class="bigals-card">
				<h2>1. Batch Photos & Splitter Engine</h2>
				<div style="margin-bottom: 15px;">
					<label><b>Batch Number (4 Digits):</b> </label>
					<input type="text" id="batchNumber" value="0012" onchange="renderWorkspace()" style="width: 80px; background:#222; color:#fff; border:1px solid #444; padding:5px;">
					<button class="button button-primary" id="btnUploadImages">Bulk Select & Upload Photos</button>
					<button class="button" id="btnLoadBatch">Load Published Batch</button>
					<label style="margin-left:10px;"><b>Images per Product:</b> </label>
					<input type="number" id="imagesPerProduct" min="1" step="1" placeholder="e.g. 5" style="width:70px; background:#222; color:#fff; border:1px solid #444; padding:5px;">
					<button class="button" id="btnAutoGroupImages">Auto Group Images</button>
					<span id="uploadStatus" style="margin-left:10px; color:#ff6b00;"></span>
				</div>
				<p style="font-size:11px; color:#aaa; margin-top:-5px;">Tip: Click on any vertical line to <b>Split</b> or <b>Merge</b> images into product groups.</p>
				<div class="photo-strip" id="photoStripContainer">
					<p style="color:#666; padding:10px;">No photos uploaded yet. Click above to load images into the splitter.</p>
				</div>
			</div>

			<!-- 2. Bulk Multi-Attribute Assignment -->
			<div class="bigals-card">
				<h2>2. Range-Based Attribute Engine</h2>
				<div class="range-grid preset-grid" style="margin-bottom:15px;">
					<div class="form-group">
						<label>Attribute Preset</label>
						<select id="attributePresetSelect">
							<option value="">Select a saved preset</option>
						</select>
					</div>
					<div class="form-group">
						<button type="button" class="btn-accent" onclick="applyAttributePreset()">Apply Preset To Range</button>
					</div>
				</div>
				<div class="range-grid">
					<div class="form-group">
						<label>Attribute</label>
						<select id="bulkField">
							<option value="title">Product Title</option>
							<option value="description">Product Description</option>
							<option value="category">Category</option>
							<option value="brand">Brand</option>
							<option value="price">Price ($)</option>
							<option value="color">Colors (Multi - comma separated)</option>
							<option value="size">Sizes (Multi - comma separated)</option>
							<option value="thickness">Thickness</option>
							<option value="joint_size">Joint Size</option>
						</select>
					</div>
					<div class="form-group">
						<label>Value</label>
						<textarea id="bulkValue" rows="3" placeholder="e.g. Product title or description"></textarea>
					</div>
					<div class="form-group">
						<label>Range Syntax</label>
						<input type="text" id="bulkRange" placeholder="e.g. 1-2, 3">
					</div>
					<div class="form-group">
						<button class="btn-accent" onclick="applyBulkRange()">Apply To Range</button>
					</div>
				</div>
			</div>

			<!-- 3. Review & Direct WP Sync Grid -->
			<div class="bigals-card">
				<h2>3. Review & Sync to WooCommerce</h2>
				<div class="grid-scroll">
					<table class="grid-table">
						<thead>
							<tr>
								<th>Item #</th>
								<th>SKU</th>
								<th>Images Count</th>
								<th>Title</th>
								<th>AI Copy</th>
								<th>Short Description</th>
								<th>Description</th>
								<th>Category</th>
								<th>Brand</th>
								<th>Price</th>
								<th>Colors</th>
								<th>Sizes</th>
								<th>Thickness</th>
								<th>Joint</th>
								<th>Variations</th>
							</tr>
						</thead>
						<tbody id="productTable">
							<!-- Dynamic Rows -->
						</tbody>
					</table>
				</div>
				<div style="margin-top: 15px; text-align: right;">
					<button class="button" id="btnGenerateAllAiCopy" onclick="generateAllAiCopy()">Generate AI Copy for All Products</button>
					<button class="btn-accent" id="btnPublishWooCommerce" onclick="publishBatchToWooCommerce()" style="padding:12px 25px; font-size:14px;">Publish All Products to WooCommerce</button>
				</div>
				<div id="publishProgressWrap" style="display:none; margin-top:15px;">
					<div style="height:18px; background:#333; border-radius:4px; overflow:hidden;">
						<div id="publishProgressBar" style="height:100%; width:0; background:#ff6b00; transition:width .2s;"></div>
					</div>
					<div id="publishProgressText" style="margin-top:6px; color:#aaa; font-size:12px;"></div>
				</div>
			</div>
		</div>

		<div id="variationDrawerBackdrop" class="variation-drawer-backdrop" onclick="closeVariationDrawer(event)">
			<div class="variation-drawer" role="dialog" aria-modal="true" aria-labelledby="variationDrawerTitle" onclick="event.stopPropagation()">
				<h2 id="variationDrawerTitle">Manage Variations</h2>
				<p id="variationDrawerSku" style="color:#aaa;"></p>
				<label for="variationColors">Colors</label>
				<textarea id="variationColors" rows="3" placeholder="Red, Blue, Green"></textarea>
				<label for="variationSizes">Sizes</label>
				<textarea id="variationSizes" rows="3" placeholder="Small, Medium, Large"></textarea>
				<label for="variationDefaultStock">Default Stock Quantity</label>
				<input id="variationDefaultStock" type="number" min="0" step="1" value="0">
				<label>Price Modifier Rule</label>
				<div class="variation-rule-row">
					<select id="variationRuleAttribute">
						<option value="size">Size</option>
						<option value="color">Color</option>
					</select>
					<input id="variationRuleValue" type="text" placeholder="XL">
					<input id="variationRuleAmount" type="number" step="0.01" placeholder="5.00">
					<button type="button" class="button" onclick="addVariationPriceRule()">Add</button>
				</div>
				<div id="variationRulesList" class="variation-rule-list"></div>
				<div id="variationMatrix" class="variation-matrix"></div>
				<p style="color:#aaa; font-size:12px;">Each color and size combination will become a variation for this product only.</p>
				<div class="variation-drawer-actions">
					<button type="button" class="btn-accent" onclick="saveVariationOverride()">Save Product Override</button>
					<button type="button" class="btn-secondary" onclick="closeVariationDrawer()">Cancel</button>
				</div>
			</div>
		</div>

		<script>
		let uploadedImages = [];
		// Array storing split status between images: true = split/new item starts, false = same item group
		let splits = []; 
		let productData = []; // Store table attribute state
		let activeVariationItem = null;
		let activeVariationRules = { price_modifiers: [], default_stock: 0 };
		let attributePresets = [];
		const restNonce = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
		const presetApiUrl = '<?php echo esc_url_raw( rest_url( 'bigals/v1/attribute-presets' ) ); ?>';
		const aiCopyApiUrl = '<?php echo esc_url_raw( rest_url( 'bigals/v1/generate-ai-copy' ) ); ?>';
		document.getElementById('variationColors').addEventListener('input', renderVariationMatrix);
		document.getElementById('variationSizes').addEventListener('input', renderVariationMatrix);
		document.getElementById('attributePresetSelect').addEventListener('change', loadSelectedPreset);
		loadAttributePresets();

		function presetValues(value) {
			return String(value || '').split(',').map(item => item.trim()).filter(Boolean);
		}

		function loadAttributePresets() {
			fetch(presetApiUrl, { headers: { 'X-WP-Nonce': restNonce } })
				.then(response => response.json().then(data => ({ ok: response.ok, data: data })))
				.then(result => {
					if (!result.ok || !result.data.success) throw new Error(result.data.message || 'Could not load presets.');
					attributePresets = result.data.presets || [];
					let select = document.getElementById('attributePresetSelect');
					select.innerHTML = '<option value="">Select a saved preset</option>' + attributePresets.map(preset => `<option value="${preset.id}">${preset.name}</option>`).join('');
				})
				.catch(error => { document.getElementById('uploadStatus').innerText = error.message; });
		}

		function loadSelectedPreset() {
			let id = document.getElementById('attributePresetSelect').value;
			let preset = attributePresets.find(item => item.id === id);
			if (!preset) return;
			document.getElementById('uploadStatus').innerText = preset.name + ' selected. Enter a range or apply to all products.';
		}

		function saveAttributePreset() {
			let name = document.getElementById('attributePresetName').value.trim();
			let colors = presetValues(document.getElementById('attributePresetColors').value);
			let sizes = presetValues(document.getElementById('attributePresetSizes').value);
			if (!name || (!colors.length && !sizes.length)) {
				alert('Enter a preset name and at least one color or size.');
				return;
			}
			fetch(presetApiUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
				body: JSON.stringify({ name: name, colors: colors, sizes: sizes })
			})
			.then(response => response.json().then(data => ({ ok: response.ok, data: data })))
			.then(result => {
				if (!result.ok || !result.data.success) throw new Error(result.data.message || 'Could not save preset.');
				loadAttributePresets();
			})
			.catch(error => alert(error.message));
		}

		function deleteAttributePreset() {
			let id = document.getElementById('attributePresetSelect').value;
			if (!id || !confirm('Delete this attribute preset?')) return;
			fetch(presetApiUrl + '/' + encodeURIComponent(id), {
				method: 'DELETE',
				headers: { 'X-WP-Nonce': restNonce }
			})
			.then(response => response.json().then(data => ({ ok: response.ok, data: data })))
			.then(result => {
				if (!result.ok || !result.data.success) throw new Error(result.data.message || 'Could not delete preset.');
				loadAttributePresets();
			})
			.catch(error => alert(error.message));
		}

		function applyAttributePreset() {
			let range = document.getElementById('bulkRange').value.trim();
			let selectedPreset = attributePresets.find(item => item.id === document.getElementById('attributePresetSelect').value);
			let colors = selectedPreset ? selectedPreset.colors : [];
			let sizes = selectedPreset ? selectedPreset.sizes : [];
			if (!colors.length && !sizes.length) {
				alert('Select or enter an attribute preset first.');
				return;
			}

			let targetItems = range
				? parseRange(range)
				: Object.keys(productData).filter(key => productData[key] && productData[key].images && productData[key].images.length).map(Number);
			if (!targetItems.length) {
				alert('No products are available in the current table.');
				return;
			}

			targetItems.forEach(itemNumber => {
				if (!productData[itemNumber]) return;
				if (colors.length) productData[itemNumber].colors = colors.slice();
				if (sizes.length) productData[itemNumber].sizes = sizes.slice();
				if (productData[itemNumber].variation_overrides) {
					delete productData[itemNumber].variation_overrides.colors;
					delete productData[itemNumber].variation_overrides.sizes;
				}
			});
			let groups = [];
			for (let index = 1; index < productData.length; index++) if (productData[index]) groups.push(productData[index].images || []);
			renderTable(groups, document.getElementById('batchNumber').value || '0012');
			document.getElementById('uploadStatus').innerText = 'Preset applied to ' + targetItems.length + ' product(s).';
		}

		function generateAiCopy(itemNumber) {
			let product = productData[itemNumber];
			let status = document.getElementById('uploadStatus');
			if (!product || !product.title) {
				status.innerText = 'Product title is required before generating AI copy.';
				return;
			}
			status.innerText = 'Generating AI copy for product ' + itemNumber + '...';
			fetch(aiCopyApiUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
				body: JSON.stringify({ title: product.title, category: product.category || '', colors: product.colors || [], sizes: product.sizes || [] })
			})
			.then(response => response.json().then(data => ({ ok: response.ok, data: data })))
			.then(result => {
				if (!result.ok || !result.data.success) throw new Error(result.data.message || 'AI copy generation failed.');
				product.short_description = result.data.short_description || '';
				product.description = result.data.description || '';
				let groups = [];
				for (let index = 1; index < productData.length; index++) if (productData[index]) groups.push(productData[index].images || []);
				renderTable(groups, document.getElementById('batchNumber').value || '0012');
				status.innerText = 'AI copy generated for product ' + itemNumber + '.';
			})
			.catch(error => { status.innerText = 'AI copy failed: ' + error.message.replace(/<[^>]*>/g, '').trim(); });
		}

		function generateAllAiCopy() {
			let items = Object.keys(productData).filter(key => productData[key] && productData[key].images && productData[key].images.length);
			let button = document.getElementById('btnGenerateAllAiCopy');
			let progressWrap = document.getElementById('publishProgressWrap');
			let progressBar = document.getElementById('publishProgressBar');
			let progressText = document.getElementById('publishProgressText');
			let status = document.getElementById('uploadStatus');

			if (!items.length) {
				status.innerText = 'No products are available for AI copy generation.';
				return;
			}
			if (items.some(item => !productData[item].title)) {
				status.innerText = 'Every product needs a title before AI copy generation.';
				return;
			}

			button.disabled = true;
			document.getElementById('btnPublishWooCommerce').disabled = true;
			progressWrap.style.display = 'block';
			progressBar.style.width = '0%';
			progressText.innerText = 'Starting AI copy generation...';

			let generateNext = function(index) {
				if (index >= items.length) {
					progressBar.style.width = '100%';
					progressText.innerText = 'AI copy generated for all ' + items.length + ' products.';
					button.disabled = false;
					document.getElementById('btnPublishWooCommerce').disabled = false;
					let groups = [];
					for (let row = 1; row < productData.length; row++) if (productData[row]) groups.push(productData[row].images || []);
					renderTable(groups, document.getElementById('batchNumber').value || '0012');
					return;
				}

				let itemNumber = Number(items[index]);
				progressBar.style.width = Math.round((index / items.length) * 100) + '%';
				progressText.innerText = 'Generating AI copy for product ' + (index + 1) + ' of ' + items.length + '...';
				fetch(aiCopyApiUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
					body: JSON.stringify({
						title: productData[itemNumber].title,
						category: productData[itemNumber].category || '',
						colors: productData[itemNumber].colors || [],
						sizes: productData[itemNumber].sizes || []
					})
				})
				.then(response => response.json().then(data => ({ ok: response.ok, data: data })))
				.then(result => {
					if (!result.ok || !result.data.success) throw new Error(result.data.message || 'AI copy generation failed.');
					productData[itemNumber].short_description = result.data.short_description || '';
					productData[itemNumber].description = result.data.description || '';
					generateNext(index + 1);
				})
				.catch(error => {
					let cleanMessage = error.message.replace(/<[^>]*>/g, '').trim();
					progressText.innerText = 'AI copy stopped on product ' + itemNumber + ': ' + cleanMessage;
					button.disabled = false;
					document.getElementById('btnPublishWooCommerce').disabled = false;
				});
			};

			generateNext(0);
		}

		document.getElementById('btnUploadImages').addEventListener('click', function(e) {
			e.preventDefault();
			let frame = wp.media({
				title: 'Select Batch Photos',
				button: { text: 'Add Photos to Batch' },
				multiple: true
			});

			frame.on('select', function() {
				let attachments = frame.state().get('selection').toJSON();
				uploadedImages = attachments.map(att => ({ id: att.id, url: att.url }));
				document.getElementById('uploadStatus').innerText = uploadedImages.length + ' Images Uploaded!';
				
				// Default: Every image has an active split (1 Image = 1 Product by default)
				splits = new Array(uploadedImages.length - 1).fill(true);
				productData = [];
				renderWorkspace();
			});

			frame.open();
		});

		document.getElementById('btnLoadBatch').addEventListener('click', function(e) {
			e.preventDefault();
			let batchNumber = document.getElementById('batchNumber').value.trim();
			let status = document.getElementById('uploadStatus');
			if (!batchNumber) {
				status.innerText = 'Enter a batch number first.';
				return;
			}

			status.innerText = 'Loading published products...';
			fetch('<?php echo esc_url_raw( rest_url( 'bigals/v1/load-batch/' ) ); ?>?batch_number=' + encodeURIComponent(batchNumber), {
				headers: { 'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>' }
			})
			.then(response => response.json().then(data => ({ ok: response.ok, data: data })))
			.then(result => {
				if (!result.ok || !result.data.success) throw new Error(result.data.message || 'Could not load batch.');
				productData = [];
				uploadedImages = [];
				result.data.products.forEach(product => {
					productData[product.item_number] = product.data;
					uploadedImages.push(...product.data.images);
				});
				splits = new Array(Math.max(0, uploadedImages.length - 1)).fill(true);
				status.innerText = result.data.products.length + ' published products loaded.';
				renderTable(result.data.products.map(product => product.data.images), batchNumber);
			})
			.catch(error => {
				status.innerText = 'Load failed: ' + error.message.replace(/<[^>]*>/g, '').trim();
			});
		});

		document.getElementById('btnAutoGroupImages').addEventListener('click', function(e) {
			e.preventDefault();
			let imagesPerProduct = Number.parseInt(document.getElementById('imagesPerProduct').value, 10);
			let status = document.getElementById('uploadStatus');

			if (!uploadedImages.length) {
				status.innerText = 'Upload images before auto grouping.';
				return;
			}
			if (!Number.isInteger(imagesPerProduct) || imagesPerProduct < 1) {
				status.innerText = 'Enter a valid images-per-product number.';
				return;
			}

			splits = new Array(Math.max(0, uploadedImages.length - 1)).fill(false);
			for (let dividerIndex = imagesPerProduct - 1; dividerIndex < splits.length; dividerIndex += imagesPerProduct) {
				splits[dividerIndex] = true;
			}
			renderWorkspace();
			let productCount = Math.ceil(uploadedImages.length / imagesPerProduct);
			status.innerText = uploadedImages.length + ' images grouped into ' + productCount + ' products.';
		});

		// Toggle Split Line when user clicks a divider
		function toggleSplit(index) {
			splits[index] = !splits[index];
			renderWorkspace();
		}

		function renderWorkspace() {
			if(uploadedImages.length === 0) return;

			let container = document.getElementById('photoStripContainer');
			container.innerHTML = '';

			// Step 1: Group Images into Items based on splits array
			let items = [];
			let currentGroup = [uploadedImages[0]];

			for (let i = 0; i < splits.length; i++) {
				if (splits[i]) {
					items.push(currentGroup);
					currentGroup = [];
				}
				currentGroup.push(uploadedImages[i + 1]);
			}
			items.push(currentGroup);
			// Remove data for products that were merged into another group.
			productData.length = items.length + 1;

			// Step 2: Render Photos & Dividers DOM
			let html = '';
			let globalImgIndex = 0;
			let batchNum = document.getElementById('batchNumber').value || '0012';

			items.forEach((group, itemIdx) => {
				let itemNumStr = String(itemIdx + 1).padStart(3, '0');

				group.forEach((imgUrl, imgInGroupIdx) => {
					let role = 'Gallery';
					if (imgInGroupIdx === 0) role = 'Main';
					else if (imgInGroupIdx === 1) role = 'Hover';

					html += `
						<div class="photo-item">
							${imgInGroupIdx === 0 ? `<span class="item-badge">Item ${itemNumStr}</span>` : ''}
							<img src="${imgUrl.url}" style="width:100%; height:100%; object-fit:cover; border-radius:4px;">
							<span class="img-role-badge">${role}</span>
						</div>
					`;

					// If not the last photo in group, add inner divider (active or inactive)
					if (globalImgIndex < splits.length) {
						let isActive = splits[globalImgIndex];
						html += `
							<div class="divider-bar ${isActive ? 'active' : ''}" onclick="toggleSplit(${globalImgIndex})">
								<div class="divider-line"></div>
							</div>
						`;
						globalImgIndex++;
					}
				});
			});

			container.innerHTML = html;

			// Step 3: Render Table Rows based on NEW item groups count
			renderTable(items, batchNum);
		}

		function renderTable(items, batchNum) {
			let table = document.getElementById('productTable');
			table.innerHTML = '';
			productData.length = items.length + 1;

			items.forEach((group, index) => {
				let itemNum = index + 1;
				let itemNumStr = String(itemNum).padStart(3, '0');
				let sku = batchNum + '-' + itemNumStr;

				// Retain existing attribute data if item existed previously
				if (!productData[itemNum]) {
					productData[itemNum] = {
						category: 'Unassigned',
						title: 'Batch ' + batchNum + ' Item ' + itemNumStr,
						short_description: '',
						description: '',
						brand: 'Unassigned',
						price: '0.00',
						colors: ['Unassigned'],
						sizes: ['Unassigned'],
						variation_overrides: {},
						thickness: 'Unassigned',
						joint_size: 'Unassigned',
						images: group
					};
				} else {
					productData[itemNum].images = group;
				}

				let data = productData[itemNum];
				let variationColors = getVariationValues(data, 'colors');
				let variationSizes = getVariationValues(data, 'sizes');

				let colorTags = variationColors.map(c => `<span class="tag tag-color">${c}</span>`).join('');
				let sizeTags = variationSizes.map(s => `<span class="tag tag-size">${s}</span>`).join('');

				table.innerHTML += `
					<tr id="row-${itemNum}">
						<td><b>${itemNumStr}</b></td>
						<td><code>${sku}</code></td>
						<td><span class="tag" style="background:#444;">${group.length} Photos</span></td>
						<td class="col-title">${data.title || ''}</td>
						<td><button type="button" class="button" onclick="generateAiCopy(${itemNum})">Generate AI Copy</button></td>
						<td class="col-short-description">${data.short_description || ''}</td>
						<td class="col-description">${data.description || ''}</td>
						<td class="col-category"><span class="tag">${data.category}</span></td>
						<td class="col-brand"><span class="tag">${data.brand}</span></td>
						<td class="col-price">$<input type="text" value="${data.price}" onchange="productData[${itemNum}].price = this.value" style="width:50px; background:#222; color:#fff; border:1px solid #444;"></td>
						<td class="col-color"><div class="tag-group">${colorTags}</div></td>
						<td class="col-size"><div class="tag-group">${sizeTags}</div></td>
						<td class="col-thickness"><span class="tag">${data.thickness}</span></td>
						<td class="col-joint_size"><span class="tag">${data.joint_size}</span></td>
						<td><button type="button" class="button" onclick="openVariationDrawer(${itemNum})">Manage Variations</button></td>
					</tr>
				`;
			});
		}

		function parseRange(rangeStr) {
			let items = [];
			let parts = rangeStr.split(',');
			parts.forEach(part => {
				if (part.includes('-')) {
					let [start, end] = part.split('-').map(Number);
					for (let i = start; i <= end; i++) items.push(i);
				} else {
					if(part.trim() !== '') items.push(Number(part.trim()));
				}
			});
			return items;
		}

		function getVariationValues(data, key) {
			let overrides = data.variation_overrides || {};
			let values = Object.prototype.hasOwnProperty.call(overrides, key) ? overrides[key] : data[key];
			if (!Array.isArray(values)) values = values ? String(values).split(',') : [];
			return values.map(value => String(value).trim()).filter(value => value && value !== 'Unassigned');
		}

		function openVariationDrawer(itemNumber) {
			let data = productData[itemNumber];
			if (!data) return;
			activeVariationItem = itemNumber;
			document.getElementById('variationDrawerTitle').innerText = 'Manage Variations - Item ' + String(itemNumber).padStart(3, '0');
			document.getElementById('variationDrawerSku').innerText = 'SKU: ' + (document.getElementById('batchNumber').value || '0012') + '-' + String(itemNumber).padStart(3, '0');
			document.getElementById('variationColors').value = getVariationValues(data, 'colors').join(', ');
			document.getElementById('variationSizes').value = getVariationValues(data, 'sizes').join(', ');
			activeVariationRules = normalizeVariationRules(data.variation_rules);
			document.getElementById('variationDefaultStock').value = activeVariationRules.default_stock;
			renderVariationRules();
			renderVariationMatrix();
			document.getElementById('variationDrawerBackdrop').style.display = 'block';
		}

		function closeVariationDrawer(event) {
			if (event && event.target !== event.currentTarget) return;
			document.getElementById('variationDrawerBackdrop').style.display = 'none';
			activeVariationItem = null;
		}

		function saveVariationOverride() {
			if (!activeVariationItem || !productData[activeVariationItem]) return;
			let colors = document.getElementById('variationColors').value.split(',').map(value => value.trim()).filter(Boolean);
			let sizes = document.getElementById('variationSizes').value.split(',').map(value => value.trim()).filter(Boolean);
			productData[activeVariationItem].variation_overrides = { colors: colors, sizes: sizes };
			activeVariationRules.default_stock = Math.max(0, Number.parseInt(document.getElementById('variationDefaultStock').value, 10) || 0);
			productData[activeVariationItem].variation_rules = activeVariationRules;
			let batchNum = document.getElementById('batchNumber').value || '0012';
			let groups = [];
			for (let index = 1; index < productData.length; index++) {
				if (productData[index]) groups.push(productData[index].images || []);
			}
			renderTable(groups, batchNum);
			closeVariationDrawer();
		}

		function normalizeVariationRules(rules) {
			rules = rules && typeof rules === 'object' ? rules : {};
			return {
				price_modifiers: Array.isArray(rules.price_modifiers) ? rules.price_modifiers.map(rule => ({
					attribute: rule.attribute === 'color' ? 'color' : 'size',
					value: String(rule.value || '').trim(),
					amount: Number(rule.amount) || 0
				})).filter(rule => rule.value) : [],
				default_stock: Math.max(0, Number.parseInt(rules.default_stock, 10) || 0)
			};
		}

		function addVariationPriceRule() {
			let attribute = document.getElementById('variationRuleAttribute').value;
			let value = document.getElementById('variationRuleValue').value.trim();
			let amount = Number.parseFloat(document.getElementById('variationRuleAmount').value);
			if (!value || Number.isNaN(amount)) return;
			activeVariationRules.price_modifiers.push({ attribute: attribute, value: value, amount: amount });
			document.getElementById('variationRuleValue').value = '';
			document.getElementById('variationRuleAmount').value = '';
			renderVariationRules();
			renderVariationMatrix();
		}

		function removeVariationPriceRule(index) {
			activeVariationRules.price_modifiers.splice(index, 1);
			renderVariationRules();
			renderVariationMatrix();
		}

		function renderVariationRules() {
			let container = document.getElementById('variationRulesList');
			container.innerHTML = activeVariationRules.price_modifiers.map((rule, index) => `
				<div class="variation-rule-item">
					<span>${rule.attribute} = ${rule.value}: ${rule.amount >= 0 ? '+' : ''}$${rule.amount.toFixed(2)}</span>
					<button type="button" class="button" onclick="removeVariationPriceRule(${index})">Remove</button>
				</div>
			`).join('');
		}

		function renderVariationMatrix() {
			let container = document.getElementById('variationMatrix');
			if (!container || !activeVariationItem || !productData[activeVariationItem]) return;
			let data = productData[activeVariationItem];
			let colors = document.getElementById('variationColors').value.split(',').map(value => value.trim()).filter(Boolean);
			let sizes = document.getElementById('variationSizes').value.split(',').map(value => value.trim()).filter(Boolean);
			colors = colors.length ? colors : [ 'Any color' ];
			sizes = sizes.length ? sizes : [ 'Any size' ];
			let lines = [ 'Generated combinations: ' + (colors.length * sizes.length), 'Default stock: ' + activeVariationRules.default_stock, '' ];
			colors.forEach(color => sizes.forEach(size => {
				let price = Number(data.price) || 0;
				activeVariationRules.price_modifiers.forEach(rule => {
					let value = 'color' === rule.attribute ? color : size;
					if (value.toLowerCase() === rule.value.toLowerCase()) price += Number(rule.amount) || 0;
				});
				lines.push(color + ' / ' + size + ' = $' + Math.max(0, price).toFixed(2));
			}));
			container.innerText = lines.join('\n');
		}

		function applyBulkRange() {
			let field = document.getElementById('bulkField').value;
			let rawVal = document.getElementById('bulkValue').value;
			let rangeStr = document.getElementById('bulkRange').value;

			if(!rawVal || !rangeStr) {
				alert("Please fill both Value and Range!");
				return;
			}

			let targetItems = parseRange(rangeStr);

			targetItems.forEach(itemNum => {
				if (productData[itemNum]) {
					if (field === 'price') {
						productData[itemNum].price = rawVal;
					} else if (field === 'color') {
						productData[itemNum].colors = rawVal.split(',').map(v => v.trim());
						if (productData[itemNum].variation_overrides) delete productData[itemNum].variation_overrides.colors;
					} else if (field === 'size') {
						productData[itemNum].sizes = rawVal.split(',').map(v => v.trim());
						if (productData[itemNum].variation_overrides) delete productData[itemNum].variation_overrides.sizes;
					} else {
						productData[itemNum][field] = rawVal;
					}
				}
			});

			let batchNum = document.getElementById('batchNumber').value || '0012';
			// Re-render table with updated attributes
			let currentItemsCount = productData.length - 1;
			let dummyGroupArray = [];
			for(let i=1; i<=currentItemsCount; i++) {
				if(productData[i]) dummyGroupArray.push(productData[i].images || []);
			}
			renderTable(dummyGroupArray, batchNum);
		}

		function publishBatchToWooCommerce() {
			let items = Object.keys(productData).filter(key => productData[key] && productData[key].images && productData[key].images.length);
			let button = document.getElementById('btnPublishWooCommerce');
			let progressWrap = document.getElementById('publishProgressWrap');
			let progressBar = document.getElementById('publishProgressBar');
			let progressText = document.getElementById('publishProgressText');
			let batchNumber = document.getElementById('batchNumber').value || '0012';

			if (!items.length) {
				alert('Please upload and split photos before publishing.');
				return;
			}

			button.disabled = true;
			progressWrap.style.display = 'block';
			progressBar.style.width = '0%';
			progressText.innerText = 'Starting product upload...';

			let publishNext = function(index) {
				if (index >= items.length) {
					progressBar.style.width = '100%';
					progressText.innerText = 'All ' + items.length + ' products and their variations were published.';
					button.disabled = false;
					return;
				}

				let itemNumber = Number(items[index]);
				let percent = Math.round((index / items.length) * 100);
				progressBar.style.width = percent + '%';
				progressText.innerText = 'Publishing product ' + (index + 1) + ' of ' + items.length + '...';

				fetch('<?php echo esc_url_raw( rest_url( 'bigals/v1/create-batch/' ) ); ?>', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>' },
					body: JSON.stringify({ batch_number: batchNumber, item_number: itemNumber, product: productData[itemNumber] })
				})
				.then(response => response.json().then(data => ({ ok: response.ok, data: data })))
				.then(result => {
					if (!result.ok || !result.data.success) throw new Error(result.data.message || 'Product upload failed.');
					publishNext(index + 1);
				})
				.catch(error => {
					let cleanMessage = error.message.replace(/<[^>]*>/g, '').trim();
					progressText.innerText = 'Upload stopped: ' + cleanMessage;
					button.disabled = false;
				});
			};

			publishNext(0);
		}
		</script>
		<?php
	}

	public function register_rest_routes() {
		register_rest_route( 'bigals/v1', '/create-batch/', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'handle_rest_batch_creation' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		) );
		register_rest_route( 'bigals/v1', '/load-batch/', array(
			'methods' => 'GET',
			'callback' => array( $this, 'handle_rest_batch_load' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		) );
		register_rest_route( 'bigals/v1', '/attribute-presets/', array(
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'handle_attribute_presets_fetch' ),
				'permission_callback' => array( $this, 'can_manage_batch' ),
			),
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'handle_attribute_preset_save' ),
				'permission_callback' => array( $this, 'can_manage_batch' ),
			)
		) );
		register_rest_route( 'bigals/v1', '/attribute-presets/(?P<id>[a-z0-9_-]+)', array(
			'methods' => 'DELETE',
			'callback' => array( $this, 'handle_attribute_preset_delete' ),
			'permission_callback' => array( $this, 'can_manage_batch' ),
		) );
		register_rest_route( 'bigals/v1', '/generate-ai-copy/', array(
			'methods' => 'POST',
			'callback' => array( $this, 'handle_generate_ai_copy' ),
			'permission_callback' => array( $this, 'can_manage_batch' ),
		) );
		register_rest_route( 'bigals/v1', '/test-gemini/', array(
			'methods' => 'POST',
			'callback' => array( $this, 'handle_test_gemini' ),
			'permission_callback' => array( $this, 'can_manage_batch' ),
		) );
	}

	public function can_manage_batch() {
		return current_user_can( 'manage_woocommerce' );
	}

	private function get_attribute_presets() {
		$presets = get_option( 'bigals_attribute_presets', array() );
		return is_array( $presets ) ? $presets : array();
	}

	private function sanitize_attribute_preset( $data, $id = '' ) {
		$name = sanitize_text_field( $data['name'] ?? '' );
		$colors = $this->clean_attribute_values( $data['colors'] ?? array() );
		$sizes = $this->clean_attribute_values( $data['sizes'] ?? array() );
		return array(
			'id' => $id ?: sanitize_title( $name ),
			'name' => $name,
			'colors' => $colors,
			'sizes' => $sizes,
		);
	}

	public function handle_attribute_presets_fetch() {
		return new WP_REST_Response( array( 'success' => true, 'presets' => array_values( $this->get_attribute_presets() ) ), 200 );
	}

	public function handle_attribute_preset_save( $request ) {
		$preset = $this->sanitize_attribute_preset( $request->get_json_params() );
		if ( ! $preset['name'] || ( empty( $preset['colors'] ) && empty( $preset['sizes'] ) ) ) {
			return new WP_Error( 'invalid_attribute_preset', 'Preset name and at least one color or size are required.', array( 'status' => 400 ) );
		}
		$presets = $this->get_attribute_presets();
		$presets[ $preset['id'] ] = $preset;
		update_option( 'bigals_attribute_presets', $presets, false );
		return new WP_REST_Response( array( 'success' => true, 'preset' => $preset ), 200 );
	}

	public function handle_attribute_preset_delete( $request ) {
		$id = sanitize_key( $request['id'] );
		$presets = $this->get_attribute_presets();
		if ( ! isset( $presets[ $id ] ) ) {
			return new WP_Error( 'preset_not_found', 'Attribute preset not found.', array( 'status' => 404 ) );
		}
		unset( $presets[ $id ] );
		update_option( 'bigals_attribute_presets', $presets, false );
		return new WP_REST_Response( array( 'success' => true, 'deleted' => $id ), 200 );
	}

	public function handle_generate_ai_copy( $request ) {
		$api_key = trim( (string) get_option( 'bigals_gemini_api_key', '' ) );
		$model = sanitize_text_field( get_option( 'bigals_gemini_model', 'gemini-3.6-flash' ) );
		if ( ! $model || 'gemini-2.0-flash' === $model || 'models/gemini-2.0-flash' === $model ) {
			$model = 'gemini-3.6-flash';
			update_option( 'bigals_gemini_model', $model, false );
		}
		$model = preg_replace( '#^models/#', '', $model );
		$data = $request->get_json_params();
		$title = sanitize_text_field( $data['title'] ?? '' );
		$category = sanitize_text_field( $data['category'] ?? '' );
		$colors = implode( ', ', $this->clean_attribute_values( $data['colors'] ?? array() ) );
		$sizes = implode( ', ', $this->clean_attribute_values( $data['sizes'] ?? array() ) );

		if ( ! $api_key ) {
			return new WP_Error( 'missing_gemini_key', 'Gemini API key is not configured. Open Batch Creator > AI Settings.', array( 'status' => 400 ) );
		}
		if ( ! $title ) {
			return new WP_Error( 'missing_product_title', 'A product title is required.', array( 'status' => 400 ) );
		}

		$prompt = "Create WooCommerce product copy for this product. Return ONLY valid JSON with exactly two string keys: short_description and description. The short description must be 1-2 concise sentences. The description must be 80-120 words, useful and accurate, with no unsupported claims. Product title: {$title}. Category: {$category}. Colors: {$colors}. Sizes: {$sizes}.";
		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key ),
			array(
				'timeout' => 45,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body' => wp_json_encode( array(
					'contents' => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
					'generationConfig' => array( 'responseMimeType' => 'application/json', 'temperature' => 0.7 ),
				) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'gemini_request_failed', $response->get_error_message(), array( 'status' => 502 ) );
		}
		$status = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 ) {
			$message = $body['error']['message'] ?? 'Gemini API request failed.';
			return new WP_Error( 'gemini_api_error', sanitize_text_field( $message ), array( 'status' => 502 ) );
		}
		$text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
		$copy = json_decode( trim( $text ), true );
		if ( ! is_array( $copy ) || ! isset( $copy['short_description'], $copy['description'] ) ) {
			return new WP_Error( 'invalid_gemini_response', 'Gemini returned an invalid copy response.', array( 'status' => 502 ) );
		}
		return new WP_REST_Response( array(
			'success' => true,
			'short_description' => sanitize_textarea_field( $copy['short_description'] ),
			'description' => wp_kses_post( $copy['description'] ),
		), 200 );
	}

	public function handle_test_gemini() {
		$api_key = trim( (string) get_option( 'bigals_gemini_api_key', '' ) );
		$model = sanitize_text_field( get_option( 'bigals_gemini_model', 'gemini-3.6-flash' ) );
		$model = preg_replace( '#^models/#', '', $model );
		$now = current_time( 'mysql' );
		if ( ! $api_key ) {
			$message = 'No Gemini API key configured.';
			update_option( 'bigals_gemini_monitor', array( 'checked_at' => $now, 'ok' => false, 'code' => 0, 'message' => $message ), false );
			return new WP_Error( 'missing_gemini_key', $message, array( 'status' => 400 ) );
		}
		$response = wp_remote_post( 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key ), array(
			'timeout' => 30,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body' => wp_json_encode( array( 'contents' => array( array( 'parts' => array( array( 'text' => 'Reply with OK.' ) ) ) ) ) ),
		) );
		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			update_option( 'bigals_gemini_monitor', array( 'checked_at' => $now, 'ok' => false, 'code' => 0, 'message' => $message ), false );
			return new WP_Error( 'gemini_connection_failed', $message, array( 'status' => 502 ) );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$message = $body['error']['message'] ?? 'Gemini API request failed.';
			$message = sanitize_text_field( $message );
			if ( 401 === $code || 403 === $code ) $message = 'API key invalid, disabled, or not authorized: ' . $message;
			if ( 429 === $code ) $message = 'Quota or rate limit reached: ' . $message;
			update_option( 'bigals_gemini_monitor', array( 'checked_at' => $now, 'ok' => false, 'code' => $code, 'message' => $message ), false );
			return new WP_Error( 'gemini_api_error', $message, array( 'status' => 502 ) );
		}
		$message = 'Gemini connection is working. Model: ' . $model;
		update_option( 'bigals_gemini_monitor', array( 'checked_at' => $now, 'ok' => true, 'code' => $code, 'message' => $message ), false );
		return new WP_REST_Response( array( 'success' => true, 'message' => $message, 'checked_at' => $now ), 200 );
	}

	public function handle_rest_batch_load( $request ) {
		$batch_number = sanitize_text_field( $request->get_param( 'batch_number' ) );
		if ( ! $batch_number ) {
			return new WP_Error( 'missing_batch_number', 'Batch number is required.', array( 'status' => 400 ) );
		}

		$products = get_posts( array(
			'post_type' => 'product',
			'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby' => 'meta_value_num',
			'order' => 'ASC',
			'meta_key' => '_sku',
			'meta_query' => array( array(
				'key' => '_sku',
				'value' => $batch_number . '-',
				'compare' => 'LIKE',
			) ),
			'fields' => 'ids',
		) );
		$result = array();
		foreach ( $products as $product_id ) {
			$product = wc_get_product( $product_id );
			$sku = $product ? $product->get_sku() : '';
			if ( ! $product || ! preg_match( '/^' . preg_quote( $batch_number, '/' ) . '-(\d+)$/', $sku, $match ) ) {
				continue;
			}
			$images = array();
			$image_ids = array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) );
			foreach ( $image_ids as $image_id ) {
				$images[] = array( 'id' => absint( $image_id ), 'url' => wp_get_attachment_url( $image_id ) );
			}
			$attributes = $product->get_attributes();
			$colors = isset( $attributes['color'] ) ? $attributes['color']->get_options() : array();
			$sizes = isset( $attributes['size'] ) ? $attributes['size']->get_options() : array();
			$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
			$saved_variation_rules = get_post_meta( $product_id, '_bigals_variation_rules', true );
			$saved_variation_rules = is_array( $saved_variation_rules ) ? $saved_variation_rules : array();
			$result[] = array(
				'item_number' => absint( $match[1] ),
				'data' => array(
					'category' => $categories ? $categories[0] : 'Unassigned',
					'title' => $product->get_name(),
					'short_description' => $product->get_short_description(),
					'description' => $product->get_description(),
					'brand' => get_post_meta( $product_id, '_bigals_brand', true ) ?: 'Unassigned',
					'price' => $product->get_price() ?: '0.00',
					'colors' => $colors ?: array( 'Unassigned' ),
					'sizes' => $sizes ?: array( 'Unassigned' ),
					'variation_overrides' => array(),
					'variation_rules' => $saved_variation_rules,
					'thickness' => get_post_meta( $product_id, '_bigals_thickness', true ) ?: 'Unassigned',
					'joint_size' => get_post_meta( $product_id, '_bigals_joint_size', true ) ?: 'Unassigned',
					'images' => $images,
				),
			);
		}

		return new WP_REST_Response( array( 'success' => true, 'products' => $result ), 200 );
	}

	public function handle_rest_batch_creation( $request ) {
		try {
			return $this->create_batch_product( $request );
		} catch ( Throwable $error ) {
			return new WP_Error( 'batch_product_error', $error->getMessage(), array( 'status' => 500 ) );
		}
	}

	private function create_batch_product( $request ) {
		$data = $request->get_json_params();
		$product_data = isset( $data['product'] ) && is_array( $data['product'] ) ? $data['product'] : array();
		$batch_number = sanitize_text_field( $data['batch_number'] ?? '' );
		$item_number = absint( $data['item_number'] ?? 0 );

		if ( ! class_exists( 'WC_Product' ) || ! $batch_number || ! $item_number || empty( $product_data['images'] ) ) {
			return new WP_Error( 'invalid_batch_product', 'Product data is incomplete or WooCommerce is not active.', array( 'status' => 400 ) );
		}

		$sku = $batch_number . '-' . str_pad( (string) $item_number, 3, '0', STR_PAD_LEFT );
		$colors = $this->clean_attribute_values( $product_data['colors'] ?? array() );
		$sizes = $this->clean_attribute_values( $product_data['sizes'] ?? array() );
		$variation_overrides = isset( $product_data['variation_overrides'] ) && is_array( $product_data['variation_overrides'] )
			? $product_data['variation_overrides']
			: array();
		$variation_colors = array_key_exists( 'colors', $variation_overrides )
			? $this->clean_attribute_values( $variation_overrides['colors'] )
			: $colors;
		$variation_sizes = array_key_exists( 'sizes', $variation_overrides )
			? $this->clean_attribute_values( $variation_overrides['sizes'] )
			: $sizes;
		$variation_rules = $this->sanitize_variation_rules( $product_data['variation_rules'] ?? array() );
		$has_variations = ! empty( $variation_colors ) || ! empty( $variation_sizes );
		$existing_product_id = wc_get_product_id_by_sku( $sku );
		$product = $existing_product_id
			? ( $has_variations ? new WC_Product_Variable( $existing_product_id ) : new WC_Product_Simple( $existing_product_id ) )
			: ( $has_variations ? new WC_Product_Variable() : new WC_Product_Simple() );
		if ( ! $product ) {
			return new WP_Error( 'product_not_found', 'Could not load the existing product for this SKU.', array( 'status' => 500 ) );
		}

		$default_title = "Batch {$batch_number} Item " . str_pad( (string) $item_number, 3, '0', STR_PAD_LEFT );
		$title = sanitize_text_field( $product_data['title'] ?? '' );
		$product->set_name( $title ?: $default_title );
		$product->set_status( 'publish' );
		$product->set_sku( $sku );
		$product->set_regular_price( wc_format_decimal( $product_data['price'] ?? 0 ) );
		$product->set_short_description( wp_kses_post( $product_data['short_description'] ?? '' ) );
		$product->set_description( wp_kses_post( $product_data['description'] ?? '' ) );

		$image_ids = array_values( array_filter( array_map( function ( $image ) {
			return absint( is_array( $image ) ? ( $image['id'] ?? 0 ) : $image );
		}, $product_data['images'] ) ) );
		if ( $image_ids ) {
			$product->set_image_id( array_shift( $image_ids ) );
			$product->set_gallery_image_ids( $image_ids );
		}

		$attributes = array();
		if ( $variation_colors ) {
			$attributes['color'] = $this->build_product_attribute( 'Color', $variation_colors );
		}
		if ( $variation_sizes ) {
			$attributes['size'] = $this->build_product_attribute( 'Size', $variation_sizes );
		}
		if ( $attributes ) {
			$product->set_attributes( $attributes );
		}

		$product_id = $product->save();
		if ( ! $product_id ) {
			return new WP_Error( 'product_not_created', 'WooCommerce could not create product.', array( 'status' => 500 ) );
		}

		$category = sanitize_text_field( $product_data['category'] ?? '' );
		if ( $category && 'Unassigned' !== $category ) {
			wp_set_object_terms( $product_id, $category, 'product_cat', false );
		}
		$brand = sanitize_text_field( $product_data['brand'] ?? '' );
		if ( $brand && 'Unassigned' !== $brand ) {
			update_post_meta( $product_id, '_bigals_brand', $brand );
		}
		update_post_meta( $product_id, '_bigals_thickness', sanitize_text_field( $product_data['thickness'] ?? '' ) );
		update_post_meta( $product_id, '_bigals_joint_size', sanitize_text_field( $product_data['joint_size'] ?? '' ) );
		update_post_meta( $product_id, '_bigals_variation_rules', $variation_rules );

		$variation_count = 0;
		if ( $has_variations ) {
			if ( $existing_product_id ) {
				$old_variation_ids = get_posts( array(
					'post_parent' => $product_id,
					'post_type' => 'product_variation',
					'post_status' => 'any',
					'numberposts' => -1,
					'fields' => 'ids',
				) );
				foreach ( $old_variation_ids as $old_variation_id ) {
					wp_delete_post( $old_variation_id, true );
				}
			}
			$variation_colors = $variation_colors ?: array( '' );
			$variation_sizes = $variation_sizes ?: array( '' );
			foreach ( $variation_colors as $color ) {
				foreach ( $variation_sizes as $size ) {
					$variation = new WC_Product_Variation();
					$variation->set_parent_id( $product_id );
					$variation->set_status( 'publish' );
					$variation->set_sku( $sku . '-' . ( $variation_count + 1 ) );
					$variation_price = $this->get_variation_price( $product_data['price'] ?? 0, $variation_rules, $color, $size );
					$variation->set_regular_price( $variation_price );
					if ( null !== $variation_rules['default_stock'] ) {
						$variation->set_manage_stock( true );
						$variation->set_stock_quantity( $variation_rules['default_stock'] );
					}
					$variation_attributes = array();
					if ( $color ) $variation_attributes['attribute_color'] = $color;
					if ( $size ) $variation_attributes['attribute_size'] = $size;
					$variation->set_attributes( $variation_attributes );
					$variation->save();
					$variation_count++;
				}
			}
		}

		return new WP_REST_Response( array( 'success' => true, 'product_id' => $product_id, 'variations' => $variation_count ), 200 );
	}

	private function clean_attribute_values( $values ) {
		$values = is_array( $values ) ? $values : explode( ',', (string) $values );
		$values = array_map( 'sanitize_text_field', $values );
		return array_values( array_filter( array_unique( $values ), function ( $value ) {
			return $value && 'Unassigned' !== $value;
		} ) );
	}

	private function sanitize_variation_rules( $rules ) {
		$rules = is_array( $rules ) ? $rules : array();
		$price_modifiers = array();
		foreach ( (array) ( $rules['price_modifiers'] ?? array() ) as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['value'] ) ) {
				continue;
			}
			$price_modifiers[] = array(
				'attribute' => 'color' === ( $rule['attribute'] ?? '' ) ? 'color' : 'size',
				'value' => sanitize_text_field( $rule['value'] ),
				'amount' => (float) ( $rule['amount'] ?? 0 ),
			);
		}
		return array(
			'price_modifiers' => $price_modifiers,
			'default_stock' => array_key_exists( 'default_stock', $rules ) ? max( 0, absint( $rules['default_stock'] ) ) : null,
		);
	}

	private function get_variation_price( $base_price, $rules, $color, $size ) {
		$price = (float) $base_price;
		foreach ( $rules['price_modifiers'] as $rule ) {
			$attribute_value = 'color' === $rule['attribute'] ? $color : $size;
			if ( 0 === strcasecmp( $attribute_value, $rule['value'] ) ) {
				$price += $rule['amount'];
			}
		}
		return wc_format_decimal( max( 0, $price ) );
	}

	private function build_product_attribute( $name, $options ) {
		$attribute = new WC_Product_Attribute();
		$attribute->set_id( 0 );
		$attribute->set_name( $name );
		$attribute->set_options( $options );
		$attribute->set_position( 0 );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		return $attribute;
	}
}

new BigAls_Batch_Creator();