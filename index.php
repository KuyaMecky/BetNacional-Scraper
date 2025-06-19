<?php
/**
 * Plugin Name: BetNacional Scraper
 * Description: A WordPress plugin to scrape content from BetNacional website.
 * Version: 1.0
 * Author: Michael Tallada
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'betnacional_admin_menu');

function betnacional_admin_menu() {
    add_options_page(
        'BetNacional Scraper Settings',
        'BetNacional Scraper',
        'manage_options',
        'betnacional-scraper',
        'betnacional_settings_page'
    );
}

function betnacional_settings_page() {
    ?>
    <div class="wrap">
        <h1>BetNacional Scraper Settings</h1>
        <h2>Available Shortcodes:</h2>
        <ul>
            <li><strong>[betnacional_home]</strong> - Main page content</li>
            <li><strong>[betnacional_sports]</strong> - Sports betting page</li>
            <li><strong>[betnacional_casino]</strong> - Casino page</li>
            <li><strong>[betnacional_promotions]</strong> - Promotions page</li>
            <li><strong>[betnacional_results]</strong> - Results page</li>
        </ul>
        <p>Use these shortcodes in your posts or pages to display the scraped content.</p>
        <p><strong>Note:</strong> All external links will be redirected to: https://seo813.pages.dev?agentid=Bet606</p>
        
        <h3>Cache Management</h3>
        <form method="post" action="">
            <?php wp_nonce_field('clear_betnacional_cache', 'cache_nonce'); ?>
            <input type="submit" name="clear_cache" class="button button-secondary" value="Clear All Cache" />
        </form>
        
        <?php
        if (isset($_POST['clear_cache']) && wp_verify_nonce($_POST['cache_nonce'], 'clear_betnacional_cache')) {
            betnacional_clear_cache();
            echo '<div class="notice notice-success"><p>Cache cleared successfully!</p></div>';
        }
        ?>
    </div>
    <?php
}

// Main page scraper shortcode
function betnacional_home_shortcode() {
    $url = 'https://betnacional.bet.br/';
    return betnacional_scrape_content($url, 'home');
}
add_shortcode('betnacional_home', 'betnacional_home_shortcode');

// Sports page scraper shortcode
function betnacional_sports_shortcode() {
    $url = 'https://betnacional.bet.br/esportes/';
    return betnacional_scrape_content($url, 'sports');
}
add_shortcode('betnacional_sports', 'betnacional_sports_shortcode');

// Casino page scraper shortcode
function betnacional_casino_shortcode() {
    $url = 'https://betnacional.bet.br/cassino/';
    return betnacional_scrape_content($url, 'casino');
}
add_shortcode('betnacional_casino', 'betnacional_casino_shortcode');

// Promotions page scraper shortcode
function betnacional_promotions_shortcode() {
    $url = 'https://betnacional.bet.br/promocoes/';
    return betnacional_scrape_content($url, 'promotions');
}
add_shortcode('betnacional_promotions', 'betnacional_promotions_shortcode');

// Results page scraper shortcode
function betnacional_results_shortcode() {
    $url = 'https://betnacional.bet.br/resultados/';
    return betnacional_scrape_content($url, 'results');
}
add_shortcode('betnacional_results', 'betnacional_results_shortcode');

// Main scraping function
function betnacional_scrape_content($url, $type = 'home') {
    // Set custom user agent and headers
    $args = array(
        'timeout'     => 30,
        'redirection' => 5,
        'httpversion' => '1.1',
        'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'headers'     => array(
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Cache-Control' => 'max-age=0',
        ),
    );

    $response = wp_remote_get($url, $args);
    
    if (is_wp_error($response)) {
        return '<div class="betnacional-error">Failed to fetch data from ' . esc_url($url) . ': ' . $response->get_error_message() . '</div>';
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        return '<div class="betnacional-error">HTTP Error ' . $response_code . ' when fetching from ' . esc_url($url) . '</div>';
    }

    $body = wp_remote_retrieve_body($response);
    
    if (empty($body)) {
        return '<div class="betnacional-error">No content received from the website.</div>';
    }

    // Load HTML
    $dom = new DOMDocument();
    libxml_use_internal_errors(true); // Suppress HTML parsing errors
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $body);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    // Try different selectors for content area
    $selectors = array(
        '//main',
        '//div[@id="main"]',
        '//div[@class="main-content"]',
        '//div[@class="content"]',
        '//div[contains(@class, "content")]',
        '//div[@class="container"]',
        '//article',
        '//section[@class="content"]',
        '//div[@role="main"]',
        '//body'
    );
    
    $content_div = null;
    foreach ($selectors as $selector) {
        $nodes = $xpath->query($selector);
        if ($nodes->length > 0) {
            $content_div = $nodes->item(0);
            break;
        }
    }
    
    if (!$content_div) {
        return '<div class="betnacional-error">Content container not found.</div>';
    }

    // List of elements to remove (ads, navigation, etc.)
    $elements_to_remove = array(
        // Common ad containers and promotional elements
        '//div[contains(@class, "ad")]',
        '//div[contains(@class, "advertisement")]',
        '//div[contains(@class, "banner")]',
        '//div[contains(@class, "promo")]',
        '//div[contains(@id, "ad")]',
        '//ins[@class="adsbygoogle"]',
        
        // Navigation and structural elements
        '//nav',
        '//header',
        '//footer',
        '//div[@class="header"]',
        '//div[@class="footer"]',
        '//div[contains(@class, "navigation")]',
        '//div[contains(@class, "menu")]',
        
        // Social media and sharing
        '//div[contains(@class, "social")]',
        '//div[contains(@class, "share")]',
        '//div[contains(@class, "sharing")]',
        
        // Forms and interactive elements that might not work
        '//form[contains(@class, "login")]',
        '//form[contains(@class, "register")]',
        '//div[contains(@class, "login")]',
        '//div[contains(@class, "register")]',
        
        // Chat and support widgets
        '//div[contains(@class, "chat")]',
        '//div[contains(@class, "support")]',
        '//div[contains(@class, "help")]',
        
        // Scripts and styles that might interfere
        '//script',
        '//style[not(@type) or @type="text/css"]',
        '//noscript',
        
        // Mobile app promotion
        '//div[contains(text(), "Download")]',
        '//div[contains(text(), "App")]',
        
        // Cookie notices and privacy
        '//div[contains(@class, "cookie")]',
        '//div[contains(@class, "privacy")]',
        '//div[contains(@class, "gdpr")]',
    );

    // Remove unwanted elements
    foreach ($elements_to_remove as $query) {
        $nodes = $xpath->query($query);
        foreach ($nodes as $node) {
            if ($node && $node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    // Update image URLs to absolute paths
    $images = $content_div->getElementsByTagName('img');
    foreach ($images as $img) {
        $src = $img->getAttribute('src');
        if (!empty($src)) {
            if (strpos($src, 'http') !== 0) {
                // Convert relative URLs to absolute
                if (strpos($src, '//') === 0) {
                    $img->setAttribute('src', 'https:' . $src);
                } elseif (strpos($src, '/') === 0) {
                    $img->setAttribute('src', 'https://betnacional.bet.br' . $src);
                } else {
                    $img->setAttribute('src', 'https://betnacional.bet.br/' . ltrim($src, '/'));
                }
            }
        }
        
        // Update srcset if present
        $srcset = $img->getAttribute('srcset');
        if (!empty($srcset)) {
            $srcset_parts = explode(',', $srcset);
            $updated_srcset = array();
            foreach ($srcset_parts as $part) {
                $part = trim($part);
                if (preg_match('/^(.+?)\s+(.+)$/', $part, $matches)) {
                    $url = trim($matches[1]);
                    $descriptor = trim($matches[2]);
                    
                    if (strpos($url, 'http') !== 0) {
                        if (strpos($url, '//') === 0) {
                            $url = 'https:' . $url;
                        } elseif (strpos($url, '/') === 0) {
                            $url = 'https://betnacional.bet.br' . $url;
                        } else {
                            $url = 'https://betnacional.bet.br/' . ltrim($url, '/');
                        }
                    }
                    $updated_srcset[] = $url . ' ' . $descriptor;
                }
            }
            if (!empty($updated_srcset)) {
                $img->setAttribute('srcset', implode(', ', $updated_srcset));
            }
        }
    }

    // Update link URLs - ALL EXTERNAL LINKS NOW REDIRECT TO YOUR SPECIFIED URL
    $redirect_url = 'https://seo813.pages.dev?agentid=Bet606';
    $links = $content_div->getElementsByTagName('a');
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        
        // Skip empty hrefs and anchor links
        if (empty($href) || strpos($href, '#') === 0) {
            continue;
        }
        
        // Store original URL for tracking
        $original_url = $href;
        
        // Convert relative URLs to absolute for tracking
        if (strpos($href, 'http') !== 0) {
            if (strpos($href, '/') === 0) {
                $original_url = 'https://betnacional.bet.br' . $href;
            } else {
                $original_url = 'https://betnacional.bet.br/' . ltrim($href, '/');
            }
        }
        
        // Redirect all links to your domain
        $link->setAttribute('href', $redirect_url);
        $link->setAttribute('target', '_blank');
        $link->setAttribute('rel', 'noopener noreferrer sponsored');
        $link->setAttribute('data-original-url', $original_url);
    }

    // Update CSS and other resource URLs
    $stylesheets = $content_div->getElementsByTagName('link');
    foreach ($stylesheets as $stylesheet) {
        if ($stylesheet->getAttribute('rel') === 'stylesheet') {
            $href = $stylesheet->getAttribute('href');
            if (!empty($href) && strpos($href, 'http') !== 0) {
                if (strpos($href, '//') === 0) {
                    $stylesheet->setAttribute('href', 'https:' . $href);
                } elseif (strpos($href, '/') === 0) {
                    $stylesheet->setAttribute('href', 'https://betnacional.bet.br' . $href);
                }
            }
        }
    }

    // Add custom CSS classes for styling
    if ($content_div->hasAttribute('class')) {
        $content_div->setAttribute('class', $content_div->getAttribute('class') . ' betnacional-content');
    } else {
        $content_div->setAttribute('class', 'betnacional-content');
    }

    // Get the HTML content
    $html_content = $dom->saveHTML($content_div);
    
    // Clean up and add wrapper
    $html_content = '<div class="betnacional-wrapper betnacional-' . esc_attr($type) . '">' . $html_content . '</div>';
    
    // Add comprehensive styling
    $html_content .= '<style>
        .betnacional-wrapper {
            max-width: 100%;
            overflow-x: auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .betnacional-wrapper img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
        }
        .betnacional-wrapper table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .betnacional-wrapper th,
        .betnacional-wrapper td {
            padding: 12px;
            border: 1px solid #e0e0e0;
            text-align: left;
        }
        .betnacional-wrapper th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .betnacional-wrapper tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .betnacional-wrapper a {
            color: #007bff;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .betnacional-wrapper a:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        .betnacional-error {
            color: #d32f2f;
            background-color: #ffebee;
            padding: 15px;
            border: 1px solid #e57373;
            border-radius: 4px;
            margin: 10px 0;
            font-weight: 500;
        }
        /* Responsive design */
        @media (max-width: 768px) {
            .betnacional-wrapper {
                font-size: 14px;
            }
            .betnacional-wrapper table,
            .betnacional-wrapper th,
            .betnacional-wrapper td {
                font-size: 12px;
                padding: 8px;
            }
        }
        /* Style for redirected links */
        .betnacional-wrapper a[data-original-url] {
            position: relative;
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white !important;
            padding: 8px 16px;
            border-radius: 4px;
            display: inline-block;
            margin: 4px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        .betnacional-wrapper a[data-original-url]:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            text-decoration: none !important;
        }
        .betnacional-wrapper a[data-original-url]::before {
            content: "🎰 ";
            margin-right: 5px;
        }
    </style>';
    
    return $html_content;
}

// Add caching functionality
function betnacional_get_cached_content($url, $type, $cache_duration = 600) { // 10 minutes cache
    $cache_key = 'betnacional_' . md5($url . $type);
    $cached_content = get_transient($cache_key);
    
    if ($cached_content !== false) {
        return $cached_content . '<div class="cache-info" style="font-size:11px;color:#666;margin-top:10px;">Content cached</div>';
    }
    
    $content = betnacional_scrape_content($url, $type);
    set_transient($cache_key, $content, $cache_duration);
    
    return $content;
}

// Cached shortcode versions
function betnacional_home_cached_shortcode() {
    return betnacional_get_cached_content('https://betnacional.bet.br/', 'home');
}
add_shortcode('betnacional_home_cached', 'betnacional_home_cached_shortcode');

function betnacional_sports_cached_shortcode() {
    return betnacional_get_cached_content('https://betnacional.bet.br/esportes/', 'sports');
}
add_shortcode('betnacional_sports_cached', 'betnacional_sports_cached_shortcode');

function betnacional_casino_cached_shortcode() {
    return betnacional_get_cached_content('https://betnacional.bet.br/cassino/', 'casino');
}
add_shortcode('betnacional_casino_cached', 'betnacional_casino_cached_shortcode');

// Clear cache function
function betnacional_clear_cache() {
    $urls = array(
        'https://betnacional.bet.br/' => 'home',
        'https://betnacional.bet.br/esportes/' => 'sports',
        'https://betnacional.bet.br/cassino/' => 'casino',
        'https://betnacional.bet.br/promocoes/' => 'promotions',
        'https://betnacional.bet.br/resultados/' => 'results',
    );
    
    foreach ($urls as $url => $type) {
        $cache_key = 'betnacional_' . md5($url . $type);
        delete_transient($cache_key);
    }
    
    return true;
}

// Add admin action to clear cache
add_action('wp_ajax_clear_betnacional_cache', 'betnacional_clear_cache');

// Activation hook
register_activation_hook(__FILE__, 'betnacional_activate');
function betnacional_activate() {
    // Clear any existing cache on activation
    betnacional_clear_cache();
    
    // Set default options if needed
    add_option('betnacional_redirect_url', 'https://seo813.pages.dev?agentid=Bet606');
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'betnacional_deactivate');
function betnacional_deactivate() {
    // Clear cache on deactivation
    betnacional_clear_cache();
}

// Add JavaScript for enhanced functionality
add_action('wp_footer', 'betnacional_add_scripts');
function betnacional_add_scripts() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Track clicks on redirected links
        const redirectedLinks = document.querySelectorAll('.betnacional-wrapper a[data-original-url]');
        redirectedLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                // Optional: Add analytics tracking here
                console.log('BetNacional link clicked:', this.getAttribute('data-original-url'));
                
                // You can add Google Analytics or other tracking here
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'click', {
                        'event_category': 'BetNacional Redirect',
                        'event_label': this.getAttribute('data-original-url'),
                        'value': 1
                    });
                }
            });
        });
        
        // Add loading indicator for dynamic content
        const wrappers = document.querySelectorAll('.betnacional-wrapper');
        wrappers.forEach(function(wrapper) {
            wrapper.style.opacity = '0';
            wrapper.style.transition = 'opacity 0.3s ease';
            setTimeout(function() {
                wrapper.style.opacity = '1';
            }, 100);
        });
    });
    </script>
    <?php
}

// Add custom CSS to admin area
add_action('admin_head', 'betnacional_admin_styles');
function betnacional_admin_styles() {
    ?>
    <style>
        .betnacional-admin-notice {
            background: #fff;
            border-left: 4px solid #28a745;
            padding: 12px;
            margin: 20px 0;
        }
        .betnacional-shortcode-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .betnacional-shortcode-list code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
    <?php
}
?>