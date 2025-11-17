# WooCommerce AI Translator

A professional WordPress plugin for automatic translation of WooCommerce websites using Polylang and OpenAI API.

## Features

### Comprehensive Translation Support
- **Posts & Pages**: Translate all WordPress content including titles, content, and excerpts
- **WooCommerce Products**: Full product translation including descriptions, attributes, and variations
- **Taxonomies**: Categories, tags, product categories, product tags, and custom taxonomies
- **SEO Meta Tags**: Automatic translation of Yoast SEO, Rank Math, and All in One SEO meta tags
- **Image Alt Tags**: Translate image alternative text for better accessibility and SEO
- **Product Attributes**: WooCommerce product attributes and variations

### Batch Translation
- **Content Scanner**: Scan your entire website to find untranslated content
- **Batch Processing**: Translate multiple items at once with queue management
- **Cost Estimation**: Preview estimated translation costs before starting
- **Priority Queue**: Set translation priorities for important content

### Manual Translation
- **Metaboxes**: Convenient metaboxes in post/page/product editors
- **Single-Click Translation**: Translate individual items with one click
- **Translation Preview**: Preview translations before applying
- **Selective Translation**: Choose specific languages for each item

### Professional Admin Interface
- **Dashboard**: Overview of translation statistics and progress
- **Batch Translation Page**: Step-by-step wizard for batch translations
- **Queue Management**: Monitor and control translation queue
- **Detailed Logs**: Track all translation activities with cost breakdown
- **Settings Page**: Configure API, translation quality, and behavior

### Advanced Features
- **Multiple AI Models**: Support for GPT-4o, GPT-4o Mini, GPT-4 Turbo, and GPT-3.5 Turbo
- **Translation Quality Control**: High-quality vs. standard translation modes
- **HTML Preservation**: Maintains all HTML formatting and structure
- **Auto-Publish**: Option to automatically publish translated content
- **Retry Logic**: Automatic retry for failed translations
- **Cost Tracking**: Detailed tracking of tokens used and costs

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- WooCommerce (latest version)
- Polylang or Polylang Pro
- OpenAI API key

## Installation

1. Upload the `woocommerce-ai-translator` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure WooCommerce and Polylang are installed and activated
4. Configure your languages in Polylang settings
5. Go to AI Translator > Settings and enter your OpenAI API key
6. Start translating!

## Configuration

### 1. Get OpenAI API Key
1. Visit [OpenAI Platform](https://platform.openai.com/)
2. Create an account or sign in
3. Navigate to API Keys section
4. Create a new API key
5. Copy the key

### 2. Configure Plugin Settings
1. Go to **AI Translator > Settings**
2. Enter your OpenAI API key
3. Choose your preferred AI model:
   - **GPT-4o** (Recommended): Best quality, balanced cost
   - **GPT-4o Mini**: Faster and cheaper, good quality
   - **GPT-4 Turbo**: Highest quality, higher cost
   - **GPT-3.5 Turbo**: Budget option
4. Configure translation settings:
   - Translation quality (High/Standard)
   - Auto-publish translated content
   - Translate SEO tags
   - Translate image alt tags
5. Set processing settings:
   - Batch size (1-50 items)
   - Maximum retries (1-10)

### 3. Set Up Languages in Polylang
1. Go to **Languages** in WordPress admin
2. Add all languages you want to translate to
3. Set default language
4. Configure language settings

## Usage

### Batch Translation

1. Go to **AI Translator > Batch Translate**
2. **Step 1**: Select content types to scan (posts, pages, products, etc.)
3. Click **Scan Website**
4. **Step 2**: Select items you want to translate
5. **Step 3**: Choose target languages
6. **Step 4**: Review cost estimate and click **Start Translation**
7. Monitor progress in **AI Translator > Queue**

### Manual Translation (Metabox)

1. Edit any post, page, or product
2. Look for **AI Translation** metabox in sidebar
3. Click **Translate to [Language]** for specific language
4. Or click **Translate to All Languages** for all missing translations
5. Wait for translation to complete

### Monitor Translations

#### Queue
- View **AI Translator > Queue** to see translation progress
- Pause/resume queue processing
- Retry failed translations
- Cancel pending translations

#### Logs
- View **AI Translator > Logs** for translation history
- See detailed statistics
- Export logs to CSV
- Track costs and token usage

## Supported SEO Plugins

The plugin automatically detects and translates SEO meta tags for:

- **Yoast SEO**
  - SEO Title
  - Meta Description
  - Focus Keyphrase
  - OpenGraph tags
  - Twitter Card tags

- **Rank Math**
  - SEO Title
  - Meta Description
  - Focus Keywords

- **All in One SEO**
  - SEO Title
  - Meta Description

## Translation Quality

### High Quality Mode
- Maintains original tone and style
- Uses natural, native-sounding language
- Preserves all formatting
- Keeps brand names unchanged
- Best for professional websites

### Standard Mode
- Fast and efficient translations
- Good quality for general content
- Lower API costs

## Cost Management

### Pricing by Model (per 1M tokens)
- **GPT-4o**: ~$6.25
- **GPT-4o Mini**: ~$0.38
- **GPT-4 Turbo**: ~$20.00
- **GPT-3.5 Turbo**: ~$1.00

### Cost Estimation
The plugin provides cost estimates before starting translations:
- Word count analysis
- Token estimation
- Total cost calculation
- Per-language breakdown

### View Costs
- Dashboard shows total costs
- Logs page shows per-translation costs
- Export logs for detailed cost analysis

## Troubleshooting

### Translations Not Starting
1. Check OpenAI API key is correct
2. Verify API key has available credits
3. Check PHP error logs
4. Ensure cron is working (`wp cron event list`)

### Translations Failing
1. Check error messages in Logs page
2. Verify content is not empty
3. Check for special characters or malformed HTML
4. Try reducing batch size

### Queue Stuck
1. Go to Queue page
2. Look for items in "Processing" status for long time
3. Click "Retry Failed" button
4. Or reset stuck items (automatically resets after 30 minutes)

### API Errors
1. **Invalid API Key**: Check your OpenAI API key in settings
2. **Rate Limit**: Reduce batch size, wait a moment
3. **Insufficient Credits**: Add credits to your OpenAI account
4. **Network Error**: Check your server's internet connection

## Development

### Filters

```php
// Modify content before translation
add_filter('wcat_content_to_translate', function($content, $post) {
    // Modify $content array
    return $content;
}, 10, 2);

// Change translation options
add_filter('wcat_translation_options', function($options) {
    $options['temperature'] = 0.5;
    return $options;
}, 10, 1);
```

### Actions

```php
// After successful translation
add_action('wcat_translation_complete', function($post_id, $target_lang, $result) {
    // Custom logic after translation
}, 10, 3);

// Before batch processing
add_action('wcat_before_batch_process', function($items) {
    // Custom logic before batch
}, 10, 1);
```

## Database Tables

The plugin creates two custom tables:

### wp_wcat_translation_queue
Stores pending and processing translations

### wp_wcat_translation_logs
Stores translation history and statistics

## Cron Jobs

The plugin uses WordPress cron:
- **wcat_process_queue**: Runs every minute to process translation queue
- **wcat_cleanup_logs**: Runs daily to clean old logs (30 days)

## Support

For issues, feature requests, or questions:
1. Check the troubleshooting section above
2. Review the plugin logs
3. Contact support with detailed error messages

## License

GPL v2 or later

## Credits

- Uses OpenAI API for translations
- Integrates with Polylang for multilingual support
- Compatible with WooCommerce
- Supports Yoast SEO, Rank Math, and All in One SEO

## Changelog

### Version 1.0.0
- Initial release
- Batch translation support
- Manual translation via metaboxes
- Queue management
- Translation logs
- SEO meta tag translation
- Image alt tag translation
- WooCommerce product support
- Multiple AI model support
- Cost tracking and estimation
