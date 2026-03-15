---
phase: 7
title: "Admin UI (React + WC Settings)"
status: pending
effort: 5h
depends_on: [2]
blocks: [8]
---

# Phase 07: Admin UI -- React + Settings

## Context Links
- [Admin UI Research](../reports/researcher-admin-ui-260315-2151.md)
- [Design Guidelines](../../docs/design-guidelines.md) -- Admin UI section

## Overview
**Priority:** P2
WooCommerce Settings tab + React-based campaign builder. Uses @wordpress/components for consistent admin UX. Communicates with REST API from Phase 02.

## Requirements
- WC Settings tab "Upsell Pro" with sections (General, Popup, Cart Upsell, BMSM, Email)
- Campaign list page with create/edit/delete
- Campaign editor: name, type, target products, rules, discount tiers
- Product picker (async search via REST)
- All settings persisted via REST API
- Responsive admin layout

## Architecture
```
WC Settings > Upsell Pro tab
  --> PHP settings page (WC_Settings_Page extension)
  --> General toggles via WC settings API
  --> "Manage Campaigns" link -> custom admin page

Custom Admin Page (#wup-admin-root):
  --> React app mounts
  --> CampaignList (list/delete/create)
  --> CampaignEditor (edit form with product picker)
  --> SettingsPage (feature config)
  --> All API calls via api-client.js -> REST endpoints
```

## Related Code Files
**Create:**
- `admin/class-wup-admin.php` (full implementation)
- `admin/class-wup-settings-page.php` (extends WC_Settings_Page)
- `admin/src/index.js`
- `admin/src/components/CampaignList.js`
- `admin/src/components/CampaignEditor.js`
- `admin/src/components/SettingsPage.js`
- `admin/src/api/api-client.js`

**Modify:**
- `includes/class-wup-loader.php` -- register admin hooks

## Implementation Steps

### 1. `admin/class-wup-admin.php`
- Namespace: `WooUpsellPro\Admin`
- Methods:
  - `register_hooks(WUP_Loader $loader)`: enqueue scripts, add menu pages
  - `enqueue_admin_assets(string $hook)`: only on our admin pages
  - `add_admin_menu()`: `add_submenu_page` under WooCommerce
  - `render_admin_page()`: output `<div id="wup-admin-root"></div>`
- Enqueue: `admin/build/index.js` + `admin/build/index.css`
- Localize: `wupAdmin` object with `rest_url`, `nonce`, `settings`

### 2. `admin/class-wup-settings-page.php`
- Extend `WC_Settings_Page`
- `__construct()`: set `$this->id = 'wup'`, `$this->label = 'Upsell Pro'`
- Register via filter: `woocommerce_get_settings_pages`
- Sections: `general`, `popup`, `cart_upsell`, `bmsm`, `email_coupon`
- Settings fields per section (using WC settings API format):
  ```php
  // General section
  ['type' => 'checkbox', 'id' => 'wup_enable_popup', 'title' => 'Enable Add-to-Cart Popup'],
  ['type' => 'checkbox', 'id' => 'wup_enable_cart_upsell', 'title' => 'Enable Cart Upsell Widget'],
  ['type' => 'checkbox', 'id' => 'wup_enable_bmsm', 'title' => 'Enable Buy More Save More'],
  ['type' => 'checkbox', 'id' => 'wup_enable_email_coupon', 'title' => 'Enable Post-Purchase Coupon'],
  ```
- Save handled automatically by WC settings API

### 3. React Entry Point (`admin/src/index.js`)
```js
import { render } from '@wordpress/element';
import { HashRouter, Route, Switch } from './router'; // simple hash router
import CampaignList from './components/CampaignList';
import CampaignEditor from './components/CampaignEditor';
import SettingsPage from './components/SettingsPage';

const App = () => {
  const [view, setView] = useState('list'); // list | edit | settings
  const [editId, setEditId] = useState(null);
  // Simple state-based routing (no react-router dep needed)
  return (
    <div className="wup-admin">
      <nav>...</nav>
      {view === 'list' && <CampaignList onEdit={id => { setEditId(id); setView('edit'); }} />}
      {view === 'edit' && <CampaignEditor id={editId} onBack={() => setView('list')} />}
      {view === 'settings' && <SettingsPage />}
    </div>
  );
};

render(<App />, document.getElementById('wup-admin-root'));
```
- No react-router dependency -- use simple state for MVP

### 4. `CampaignList.js`
- Fetch campaigns via `GET /wup/v1/campaigns`
- Display as table: Name | Type | Status | Actions (Edit/Delete)
- "Create New" button -> opens CampaignEditor with `id=null`
- Delete with confirmation dialog
- Use `@wordpress/components`: `Button`, `Spinner`, `Notice`
- Empty state message when no campaigns

### 5. `CampaignEditor.js`
- Form fields:
  - Campaign name (`TextControl`)
  - Type (`SelectControl`: popup, cart_upsell, bmsm, email_coupon)
  - Status (`ToggleControl`: active/paused)
  - Target products (`FormTokenField` with async search)
  - Target categories (`FormTokenField`)
  - Discount tiers (dynamic rows, only for BMSM type):
    - Qty input + Discount % input + Remove button
    - "+ Add Tier" button
  - Campaign-specific settings based on type
- Save: `POST /wup/v1/campaigns` (create) or `PUT /wup/v1/campaigns/{id}` (update)
- Validation: name required, at least 1 product or category
- Use `@wordpress/components`: `TextControl`, `SelectControl`, `ToggleControl`, `Panel`, `PanelBody`, `FormTokenField`, `Button`, `Notice`

### 6. Product Picker (async search)
- `FormTokenField` with async suggestions
- On input change: debounce 300ms, `GET /wup/v1/products?search=term`
- Display: product name + SKU
- Store product IDs in campaign meta

### 7. `SettingsPage.js`
- Fetch settings: `GET /wup/v1/settings`
- Sections matching WC settings: General, Popup, Cart Upsell, BMSM, Email
- Fields:
  - Popup: auto_dismiss_seconds (number), heading text
  - Cart Upsell: heading text, max products (2-5)
  - BMSM: default tiers (editable tier rows), enable per-product override
  - Email: discount type/amount, expiry days, min order, email heading
- Save: `POST /wup/v1/settings`
- Success/error notices via `@wordpress/components` `Notice`

### 8. `api/api-client.js`
```js
import apiFetch from '@wordpress/api-fetch';

const API_BASE = '/wup/v1';

export const getCampaigns = (params) => apiFetch({ path: `${API_BASE}/campaigns`, ...params });
export const getCampaign = (id) => apiFetch({ path: `${API_BASE}/campaigns/${id}` });
export const createCampaign = (data) => apiFetch({ path: `${API_BASE}/campaigns`, method: 'POST', data });
export const updateCampaign = (id, data) => apiFetch({ path: `${API_BASE}/campaigns/${id}`, method: 'PUT', data });
export const deleteCampaign = (id) => apiFetch({ path: `${API_BASE}/campaigns/${id}`, method: 'DELETE' });
export const searchProducts = (search) => apiFetch({ path: `${API_BASE}/products?search=${search}` });
export const getSettings = () => apiFetch({ path: `${API_BASE}/settings` });
export const saveSettings = (data) => apiFetch({ path: `${API_BASE}/settings`, method: 'POST', data });
```
- `@wordpress/api-fetch` handles nonce automatically when properly enqueued

### 9. Webpack Config
- Custom `webpack.config.js` extending `@wordpress/scripts/config/webpack.config`:
  - Entry: `{ admin: './admin/src/index.js' }`
  - Output: `admin/build/`
- Frontend JS handled separately (plain files, no React)

## Todo
- [ ] Implement WUP_Admin class (menu, enqueue, render)
- [ ] Implement WC Settings page with sections
- [ ] Create React entry point and simple routing
- [ ] Build CampaignList component
- [ ] Build CampaignEditor with product picker
- [ ] Build SettingsPage component
- [ ] Create api-client.js wrapper
- [ ] Configure webpack for admin build
- [ ] Test CRUD operations end-to-end
- [ ] Test settings save/load

## Success Criteria
- WC Settings tab "Upsell Pro" shows with all sections
- Feature toggles save/load correctly
- Campaign list displays all campaigns
- Create/edit campaign works with product picker
- Tier configuration works for BMSM campaigns
- Settings page saves all configuration
- Admin UI responsive on smaller screens

## Risk Assessment
| Risk | Impact | Mitigation |
|------|--------|------------|
| @wordpress/scripts version conflicts | Build fails | Pin versions in package.json |
| FormTokenField performance on large catalogs | Slow search | Debounce + limit results to 20 |
| WC settings page conflicts with other plugins | Tab missing | Use unique `wup` ID |
