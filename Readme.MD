# **BetNacional Scraper**
## **A WordPress plugin to scrape content from BetNacional website.**
I'll update the plugin to redirect all detected links to your specific URL with the agent ID. Let me modify the redirect URL in the code:Perfect! I've updated the plugin to redirect all detected links to your specific URL: `sample link here`

## **What Changed:**

1. **Admin Page**: Updated the note to show the correct redirect URL
2. **Main Scraping Function**: Changed the `$redirect_url` variable to your specified URL
3. **Plugin Activation**: Updated the default option to store your redirect URL

## **How It Works:**

- **Every link** found during scraping (buttons, text links, images links, etc.) will be redirected to: `sample link here`
- The original URL is preserved in a `data-original-url` attribute for tracking purposes
- Links open in new tabs with `target="_blank"` and proper `rel` attributes
- All redirected links get special styling (green gradient buttons with casino emoji)

## **Link Processing Features:**

✅ **Absolute URLs** → Redirected to your URL  
✅ **Relative URLs** → Converted to absolute, then redirected  
✅ **Anchor links** (#) → Skipped (won't be redirected)  
✅ **Empty links** → Skipped  
✅ **Original URL tracking** → Stored for analytics  

Now when users click any link from the scraped BetNacional content, they'll be directed to your affiliate URL with the Bet606 agent ID, ensuring you get credit for all traffic generated through your scraped content.

The plugin is ready to use with your specific affiliate tracking requirements!