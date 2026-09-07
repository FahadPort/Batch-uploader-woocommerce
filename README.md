# Big Al's Batch Product Creator

WooCommerce ke liye ye plugin images ko product groups mein divide karke products aur variations bulk mein create/update karta hai.

## Plugin Kya Karta Hai

### 1. Batch photos select karna

- WordPress Media Library se multiple photos select hoti hain.
- Har selected photo ka attachment ID aur URL store hota hai.
- Pehli photo product ki main image banti hai.
- Baqi photos gallery images ke taur par save hoti hain.
- Doosri photo ko UI mein `Hover` role dikhaya jata hai, lekin WooCommerce mein ye gallery image hi rehti hai.

### 2. Photos ko products mein split ya merge karna

- Start mein har photo ko alag product maana jata hai.
- Photos ke darmiyan orange divider par click karke split/merge kiya ja sakta hai.
- Active divider ka matlab naya product.
- Inactive divider ka matlab photos ek hi product ka group.
- Table mein sirf current active product groups rehte hain.

Example:

- 5 photos aur 5 active splits = 5 products
- 5 photos ko 2 groups mein merge karna = 2 products
- Publish par sirf 2 products create/update honge

### 3. Automatic image grouping

Bohat sari images ko fixed group size ke hisaab se products mein divide karne ke liye:

1. Images select karein.
2. `Images per Product` field mein number enter karein, example: `5`.
3. `Auto Group Images` click karein.

Example:

- 50 images + `5` images per product = 10 products
- 22 images + `5` images per product = 5 products; last product mein 2 images

Auto grouping ke baad bhi divider lines par click karke kisi group ko manually split ya merge kiya ja sakta hai.

### 4. Product SKU banana

SKU is format mein banta hai:

`BATCH-ITEM`

Example:

- Batch number: `0012`
- Product 1: `0012-001`
- Product 2: `0012-002`

SKU existing product ko dobara identify karne ke liye bhi use hota hai.

### 5. Range-based bulk editing

Attribute select karke value aur item range enter karein.

Supported fields:

- Product Title
- Product Description
- Category
- Brand
- Price
- Colors
- Sizes
- Thickness
- Joint Size

Each product row also has a `Manage Variations` button. This opens a product-specific drawer where colors and sizes can be overridden without changing the global Range Engine values. The override is sent as `variation_overrides` in the product payload and is used only for that product's variation combinations.

The drawer also contains a Variation Rule Engine:

- Automatically previews and generates every color/size combination.
- Adds multiple price rules, for example `size = XL` with `+5.00`.
- Sets a default stock quantity for every generated variation.
- Persists rules in `variation_rules` and `_bigals_variation_rules`.

Example payload:

```json
{
  "variation_overrides": {
    "colors": ["Red", "Blue", "Green"],
    "sizes": ["M", "XL"]
  },
  "variation_rules": {
    "price_modifiers": [
      {"attribute": "size", "value": "XL", "amount": 5}
    ],
    "default_stock": 10
  }
}
```

Range examples:

- `1-2` = products 1 aur 2
- `1-3, 5` = products 1, 2, 3 aur 5

Product title aur description bhi selected range par apply kiye ja sakte hain. `Product Description` ke liye value field mein description text enter karein; selected products ki existing descriptions replace ho jayengi.

Colors aur sizes comma-separated values accept karte hain:

- Colors: `Red, Yellow, Green`
- Sizes: `Large, Medium, Small`

`Apply To Range` sirf current table ke active products par apply hota hai.

### 6. WooCommerce products publish karna

`Publish All Products to WooCommerce` button:

- Products ko ek ek karke REST API ke zariye publish karta hai.
- Progress bar current upload status dikhati hai.
- Product ke liye variable product tab banta hai jab colors ya sizes assigned hon.
- Simple product tab banta hai jab colors aur sizes assigned na hon.
- Category WooCommerce product category mein save hoti hai.
- Brand `_bigals_brand` custom product meta mein save hota hai.
- Thickness `_bigals_thickness` meta mein save hoti hai.
- Joint size `_bigals_joint_size` meta mein save hota hai.
- Product title WooCommerce product name ke taur par save hota hai.
- Product short description WooCommerce short description ke taur par save hoti hai.
- Product description WooCommerce product description ke taur par save hoti hai.

`Generate AI Copy for All Products` button current table ke tamam products ke titles ke liye sequentially short aur long descriptions generate karta hai. Progress bar generation status dikhati hai aur generation ke dauran publish button disabled rehta hai. Har row ke `Generate AI Copy` button se individual product copy bhi generate ki ja sakti hai.

AI copy generation Gemini API ke RPM/RPD limits ke subject hoti hai. Free tier mein agar requests-per-minute limit hit ho jaye to next request `429` error de sakti hai; daily quota complete hone par next reset period tak wait karna hota hai.

## Variations Kaise Banti Hain

Colors aur sizes ki har combination ek variation banti hai.

Formula:

`variation count = colors count x sizes count`

Examples:

- 3 colors x 3 sizes = 9 variations
- 3 colors aur koi size nahi = 3 variations
- 2 sizes aur koi color nahi = 2 variations

Har variation ko parent SKU ke sath suffix milta hai:

- `0012-001-1`
- `0012-001-2`
- `0012-001-3`

## Published Products Bulk Edit Karna

1. Batch number enter karein, jaise `0012`.
2. `Load Published Batch` click karein.
3. Existing products table mein load ho jayenge.
4. Attribute, value aur range select karein.
5. `Apply To Range` click karein.
6. `Publish All Products to WooCommerce` click karein.

Existing products same SKU par update hote hain. Duplicate parent products create nahi hone chahiye.

Agar colors ya sizes change kiye jayein, existing variations delete karke new combinations create ki jati hain.

## Admin Pages

### Batch Creator

Main workflow page jahan images, product grouping, Range Engine, presets, AI copy, variations aur WooCommerce publishing manage hoti hai.

### Attribute Presets

`Batch Creator > Attribute Presets` se frequently used color/size combinations save, view aur delete kiye ja sakte hain. Main Batch Creator page par dropdown se preset select karke range ya current table ke tamam products par apply kiya ja sakta hai.

### AI Settings

`Batch Creator > AI Settings` mein Gemini API key aur model configure kiye jate hain. API key server-side WordPress option mein rakhi jati hai aur browser product payload mein expose nahi hoti.

`Test API Connection` button ek minimal real request se key/model status check karta hai aur last check time/status save karta hai. Ye invalid key, disabled key, connection failure, quota aur rate-limit errors report karta hai.

Google AI Studio:

https://aistudio.google.com/apikey

## Important Requirements

- WooCommerce active hona chahiye.
- User ke paas `manage_woocommerce` capability honi chahiye.
- Photos WordPress Media Library attachments honi chahiye.
- Batch number consistent rakhna chahiye, kyunki SKU isi se banta hai.
- Price numeric format mein enter karein, example: `49.99`.
- `Unassigned` colors/sizes variation options nahi banate.

## REST Routes

Plugin protected REST routes register karta hai. Sab routes `manage_woocommerce` capability aur WordPress REST nonce require karte hain.

- `POST /wp-json/bigals/v1/create-batch/`
  - Product create ya update karta hai.
  - Parent product aur variations save karta hai.

- `GET /wp-json/bigals/v1/load-batch/`
  - Batch number ke SKU prefix se existing products load karta hai.

- `POST /wp-json/bigals/v1/generate-ai-copy/`
  - Product title aur attributes se short/long copy generate karta hai.

- `POST /wp-json/bigals/v1/test-gemini/`
  - Gemini API key/model ka live health check karta hai.

### Attribute Preset Manager API

Presets `wp_options` mein `bigals_attribute_presets` option ke andar save hote hain. Sab routes `manage_woocommerce` capability aur `X-WP-Nonce` require karte hain.

- `GET /wp-json/bigals/v1/attribute-presets/`
  - All saved presets return karta hai.
- `POST /wp-json/bigals/v1/attribute-presets/`
  - Preset save/update karta hai.
- `DELETE /wp-json/bigals/v1/attribute-presets/{id}`
  - One preset delete karta hai.

WordPress admin mein `Batch Creator > Attribute Presets` submenu se presets create aur delete kiye ja sakte hain. Main `Batch Creator` page par dropdown se saved preset select karke range enter karein aur `Apply Preset To Range` click karein. Range blank ho to preset current table ke tamam products par apply hota hai.

Save payload:

```json
{
  "name": "Apparel",
  "colors": ["Red", "Blue", "Green"],
  "sizes": ["S", "M", "L", "XL"]
}
```

React dropdown example:

```jsx
import { useEffect, useState } from 'react';

export function AttributePresetSelect({ apiUrl, nonce, onSelect }) {
  const [presets, setPresets] = useState([]);
  const [selectedId, setSelectedId] = useState('');

  useEffect(() => {
    fetch(`${apiUrl}/attribute-presets/`, {
      headers: { 'X-WP-Nonce': nonce },
    })
      .then(response => response.json())
      .then(data => setPresets(data.presets || []));
  }, [apiUrl, nonce]);

  function handleChange(event) {
    const id = event.target.value;
    setSelectedId(id);
    const preset = presets.find(item => item.id === id);
    if (preset) {
      onSelect(preset);
    }
  }

  return (
    <select value={selectedId} onChange={handleChange}>
      <option value="">Select a saved preset</option>
      {presets.map(preset => (
        <option key={preset.id} value={preset.id}>{preset.name}</option>
      ))}
    </select>
  );
}
```

### Gemini AI Product Copy

AI copy use karne ke liye Google AI Studio par API key banayein:

https://aistudio.google.com/apikey

WordPress admin mein `Batch Creator > AI Settings` open karke key save karein. Product table mein `Generate AI Copy` click karne se title, category, colors aur sizes Gemini endpoint ko bheje jate hain. Endpoint short description aur long description return karta hai; publish ke waqt dono WooCommerce product fields mein save hote hain.

AI Settings page par `Test API Connection` button bhi available hai. Ye real minimal Gemini request bhej kar key/model status monitor karta hai aur last check time save karta hai. Invalid key, disabled key, connection failure, quota exhaustion, aur rate-limit responses alag message ke sath show hote hain.

Gemini API key ki fixed expiry date aur exact remaining free requests API response mein normally available nahi hoti. Free-tier quota/rate limits aur billing status Google AI Studio ke usage/billing dashboard par check karein:

https://aistudio.google.com/

AI endpoint:

`POST /wp-json/bigals/v1/generate-ai-copy/`

Request:

```json
{
  "title": "Lightweight Low Top Lace Up Womens Sneaker",
  "category": "Shoes",
  "colors": ["Red", "Blue"],
  "sizes": ["S", "M", "L"]
}
```

`onSelect` se Range Engine fields populate kiye ja sakte hain:

```jsx
<AttributePresetSelect
  apiUrl="/wp-json/bigals/v1"
  nonce={wpApiSettings.nonce}
  onSelect={preset => {
    setBulkColors(preset.colors.join(', '));
    setBulkSizes(preset.sizes.join(', '));
  }}
/>
```

AI aur preset routes sirf WooCommerce managers ke liye available hain aur WordPress REST nonce use karte hain.

## Error Handling

- Publish ke waqt progress bar par error message show hota hai.
- WordPress ke generic HTML error tags ko UI message se remove kiya jata hai.
- Partial publish ke baad dobara publish karne par same SKU ka product reuse hota hai.
- Existing product ki purani variations remove karke fresh variations banayi jati hain.
- AI errors row number/status area mein readable message ke sath show hote hain.
- Gemini quota/rate-limit errors monitoring status mein record hote hain.

## Review Table UI

- Review table wide layout mein render hoti hai taa-ke title aur descriptions readable rahen.
- Table ke andar horizontal scrolling available hai.
- Header sticky rehta hai jab table scroll hoti hai.
- Alternating rows, hover state aur wrapped description columns scanning ko easier banate hain.

## Typical Workflow

1. Batch number enter karein.
2. Photos select karein.
3. Dividers click karke product groups banayein.
4. Category, brand, price, colors aur sizes range ke zariye assign karein.
5. Zaroorat ho to `Manage Variations` se kisi individual product ke colors, sizes, price rules aur stock set karein.
6. AI Settings configure karne ke baad individual ya all-products AI copy generate karein.
7. Table review karein.
8. Publish button click karein.
9. Progress bar complete hone ka wait karein.
10. Baad mein batch number enter karke `Load Published Batch` se products dobara edit karein.
