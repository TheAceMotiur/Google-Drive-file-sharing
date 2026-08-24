# 🚀 FreeNetly API - Quick Setup Guide

## Step 1: Update Nginx Configuration

Add the API routes to your Nginx configuration (already updated in `nginx.conf`):

```nginx
# Add these lines to your server block
location ~ ^/api/v1/upload$ {
    rewrite ^/api/v1/upload$ /api/v1/upload.php last;
}

location ~ ^/api/v1/file/([a-f0-9]{12})$ {
    rewrite ^/api/v1/file/([a-f0-9]{12})$ /api/v1/file.php?id=$1 last;
}

location ~ ^/api/v1/file$ {
    rewrite ^/api/v1/file$ /api/v1/file.php last;
}
```

Then reload Nginx:
```bash
sudo nginx -t
sudo systemctl reload nginx
```

## Step 2: Initialize Database

The API tables will be created automatically when you first access the admin panel.

Or manually create tables:
```bash
cd /path/to/FreeNetly
mysql -u your_user -p your_database < database/04_api_keys.sql
```

## Step 3: Access Admin Panel

1. Open your browser and go to: **https://freenetly.com/api.php**

2. Login with your admin credentials (from `config.php`):
   - Username: `TheAceMotiur`
   - Password: `AmiMotiur27@`

## Step 4: Generate Your First API Key

1. Click on the **"🔑 API Keys"** tab
2. Fill in the form:
   - **Key Name**: e.g., "My Website"
   - **Website URL**: e.g., "https://mysite.com"
   - **Rate Limit**: Default is 100 requests/hour
3. Click **"Generate API Key"**
4. Copy your API key (starts with `ok_`)

## Step 5: Test the API

### Option A: Use the Example HTML File

1. Open `api/example.html` in your browser
2. Replace the `apiKey` value with your generated API key (around line 250)
3. Try uploading a file!

### Option B: Test with cURL

```bash
# Upload a file
curl -X POST https://freenetly.com/api/v1/upload.php \
  -H "X-API-Key: YOUR_API_KEY_HERE" \
  -F "file=@/path/to/your/file.pdf"

# Get file info
curl -X GET https://freenetly.com/api/v1/file.php?id=FILE_ID_HERE \
  -H "X-API-Key: YOUR_API_KEY_HERE"
```

### Option C: Test with JavaScript

```javascript
const formData = new FormData();
formData.append('file', yourFileBlob);

const response = await fetch('https://freenetly.com/api/v1/upload.php', {
  method: 'POST',
  headers: {
    'X-API-Key': 'YOUR_API_KEY_HERE'
  },
  body: formData
});

const data = await response.json();
console.log('Download URL:', data.download_url);
```

## Step 6: Integrate with Your Child Website

Use the `api/example.html` as a template for your own website:

1. Copy the HTML structure
2. Add your API key
3. Customize the styling to match your website
4. Deploy to your website

## 📁 File Structure

```
FreeNetly/
├── api/
│   ├── v1/
│   │   ├── upload.php      # Upload endpoint
│   │   └── file.php        # File info endpoint
│   ├── .htaccess           # Apache configuration
│   ├── README.md           # API documentation
│   └── example.html        # Vue.js integration example
├── database/
│   └── 04_api_keys.sql     # API tables schema
├── api.php                 # Admin panel with docs
├── api_auth.php            # Authentication middleware
├── config.php              # Updated with 250+ file extensions
└── nginx.conf              # Updated with API routes
```

## 🔐 Security Notes

1. **Keep your API keys secret** - Never commit them to public repositories
2. **Use HTTPS** - Always serve your API over HTTPS
3. **Rate limiting** - Adjust rate limits per key as needed
4. **Monitor usage** - Check the admin panel for suspicious activity
5. **Revoke keys** - Deactivate or delete compromised keys immediately

## 🎯 Next Steps

1. ✅ Configure Nginx and reload
2. ✅ Access admin panel at `/api.php`
3. ✅ Generate API keys
4. ✅ Test with example.html or cURL
5. ✅ Integrate into your child websites
6. ✅ Monitor usage in admin panel

## 💡 Tips

- **Custom Rate Limits**: Set higher limits for trusted websites
- **Multiple Keys**: Generate separate keys for each website
- **Activity Tracking**: Check "Last used" timestamps in admin panel
- **Key Names**: Use descriptive names to identify usage
- **Testing**: Use the example.html file during development

## 🐛 Troubleshooting

### API returns 401 "Invalid API key"
- Verify your API key is correct (starts with `ok_`)
- Check if key is active in admin panel
- Ensure `X-API-Key` header is being sent

### API returns 429 "Rate limit exceeded"
- Wait for the hour to reset
- Or increase rate limit in admin panel

### Upload fails with 413 error
- Check Nginx `client_max_body_size` setting
- Verify PHP `upload_max_filesize` and `post_max_size`

### Can't access admin panel
- Verify admin credentials in `config.php`
- Check if session cookies are enabled
- Clear browser cache and try again

## 📚 Documentation

Full API documentation is available at: **https://freenetly.com/api.php**

The documentation includes:
- ✅ API endpoint details
- ✅ Request/response examples
- ✅ Error codes and handling
- ✅ Code examples in multiple languages
- ✅ Vue.js integration samples

## 🎉 You're All Set!

Your FreeNetly API is now ready to use. Start integrating file uploads into your applications!

For support or questions, check the documentation or review the code comments.

---

**Powered by FreeNetly © 2026**
