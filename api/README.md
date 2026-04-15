# OneNetly API

Complete API implementation for programmatic file uploads and management.

## 🚀 Features

- **API Key Authentication** - Secure access with revocable keys
- **Rate Limiting** - Protect your server from abuse (100 requests/hour default)
- **Usage Analytics** - Track uploads, downloads, and API usage
- **Vue.js Dashboard** - Beautiful admin panel for key management
- **RESTful API** - Clean, intuitive endpoints
- **CORS Support** - Use from any origin
- **Comprehensive Documentation** - Built-in docs with code examples

## 📋 Setup

### 1. Run Database Migration

The API tables will be created automatically when you first access `api.php`. Or manually run:

```sql
source database/04_api_keys.sql
```

### 2. Configure Nginx (Optional - for clean URLs)

Add to your nginx configuration:

```nginx
location /api/v1 {
    rewrite ^/api/v1/upload$ /api/v1/upload.php last;
    rewrite ^/api/v1/file/([a-f0-9]{12})$ /api/v1/file.php?id=$1 last;
}
```

### 3. Access Admin Panel

Visit: `https://onenetly.com/api.php`

Login with admin credentials from `config.php`:
- Username: (defined in ADMIN_USERNAME)
- Password: (defined in ADMIN_PASSWORD)

## 🔑 API Endpoints

### POST /api/v1/upload.php
Upload a file and get download link.

**Headers:**
```
X-API-Key: your_api_key_here
Content-Type: multipart/form-data
```

**Body:**
```
file: (binary file data)
```

**Response:**
```json
{
  "success": true,
  "file_id": "a1b2c3d4e5f6",
  "filename": "document.pdf",
  "size": 1048576,
  "mime_type": "application/pdf",
  "download_url": "https://onenetly.com/download/a1b2c3d4e5f6",
  "uploaded_at": "2026-03-03T10:30:00+00:00",
  "expires_in_days": 90
}
```

### GET /api/v1/file.php?id={file_id}
Get file information.

**Headers:**
```
X-API-Key: your_api_key_here
```

**Response:**
```json
{
  "success": true,
  "file_id": "a1b2c3d4e5f6",
  "filename": "document.pdf",
  "size": 1048576,
  "size_human": "1.00 MB",
  "mime_type": "application/pdf",
  "download_url": "https://onenetly.com/download/a1b2c3d4e5f6",
  "uploaded_at": "2026-03-03 10:30:00",
  "download_count": 5,
  "last_downloaded_at": "2026-03-03 15:45:00",
  "expires_after_days": 90
}
```

### POST /api/v1/verify.php
Batch verify if multiple files exist (useful for synchronization).

**Headers:**
```
X-API-Key: your_api_key_here
Content-Type: application/json
```

**Request Body:**
```json
{
  "file_ids": ["a1b2c3d4e5f6", "xyz789ghi012", "abc123def456"]
}
```

**Response:**
```json
{
  "success": true,
  "results": {
    "a1b2c3d4e5f6": {
      "exists": true,
      "filename": "document.pdf",
      "size": 1048576,
      "upload_date": "2026-03-03 10:30:00"
    },
    "xyz789ghi012": {
      "exists": false
    },
    "abc123def456": {
      "exists": true,
      "filename": "image.jpg",
      "size": 524288,
      "upload_date": "2026-03-02 14:20:00"
    }
  },
  "summary": {
    "total_checked": 3,
    "exists": 2,
    "missing": 1
  }
}
```

**Features:**
- Check up to 2 million files in a single request
- Returns detailed info for existing files
- Perfect for sync operations and cleanup tasks
- Much faster than checking files individually
- Optimized database queries with automatic chunking

## 💻 Usage Examples

### JavaScript / Node.js

```javascript
const formData = new FormData();
formData.append('file', fileBlob);

const response = await fetch('https://onenetly.com/api/v1/upload.php', {
  method: 'POST',
  headers: {
    'X-API-Key': 'your_api_key_here'
  },
  body: formData
});

const data = await response.json();
console.log('Download URL:', data.download_url);
```

### PHP

```php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://onenetly.com/api/v1/upload.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: your_api_key_here']);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => new CURLFile($filePath)]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$data = json_decode($response, true);

echo "Download URL: " . $data['download_url'];
```

### Python

```python
import requests

headers = {'X-API-Key': 'your_api_key_here'}
files = {'file': open('/path/to/file.pdf', 'rb')}

response = requests.post(
    'https://onenetly.com/api/v1/upload.php',
    headers=headers,
    files=files
)

data = response.json()
print(f"Download URL: {data['download_url']}")
```

## 🔐 Security Features

- **API Key Validation** - Every request requires valid key
- **Rate Limiting** - Prevents abuse (configurable per key)
- **Activity Logging** - Track all API usage
- **Key Deactivation** - Instantly revoke access
- **Secure Headers** - CORS, content-type validation
- **File Type Filtering** - Block dangerous executables
- **Size Limits** - Configurable upload limits

## 📊 Admin Features

- **Generate API Keys** - Create keys with custom names and limits
- **Usage Statistics** - Monitor uploads, downloads, and API calls
- **Key Management** - Activate, deactivate, or delete keys
- **Rate Limit Control** - Set custom limits per key
- **Activity Tracking** - See when keys were last used

## ⚙️ Configuration

Edit `config.php` to customize:

- `MAX_FILE_SIZE_MB` - Maximum upload size (default: 5120 MB)
- `ALLOWED_EXTENSIONS` - Permitted file types
- `BLOCKED_EXTENSIONS` - Security-blocked types
- `EXPIRY_DAYS` - Auto-deletion after inactivity (default: 90 days)

## 📝 Database Tables

### api_keys
Stores API authentication keys and usage stats.

### api_usage_logs
Tracks every API request for analytics and rate limiting.

## 🎯 Integration Example (Child Website)

Create a simple upload form on your child website:

```html
<!DOCTYPE html>
<html>
<head>
    <title>File Upload</title>
</head>
<body>
    <input type="file" id="fileInput">
    <button onclick="uploadFile()">Upload</button>
    <div id="result"></div>

    <script>
    async function uploadFile() {
        const fileInput = document.getElementById('fileInput');
        const file = fileInput.files[0];
        
        if (!file) {
            alert('Please select a file');
            return;
        }
        
        const formData = new FormData();
        formData.append('file', file);
        
        try {
            const response = await fetch('https://onenetly.com/api/v1/upload.php', {
                method: 'POST',
                headers: {
                    'X-API-Key': 'YOUR_API_KEY_HERE'
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('result').innerHTML = 
                    `<a href="${data.download_url}" target="_blank">Download: ${data.filename}</a>`;
            } else {
                alert('Upload failed: ' + data.error);
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }
    </script>
</body>
</html>
```

## 🐛 Troubleshooting

### "Invalid or inactive API key"
- Check your API key is correct
- Verify key is active in admin panel
- Ensure X-API-Key header is set

### "Rate limit exceeded"
- Wait for the hour window to reset
- Or increase rate limit in admin panel

### "File type not allowed"
- Check ALLOWED_EXTENSIONS in config.php
- Verify file isn't in BLOCKED_EXTENSIONS

## 📞 Support

For issues or questions:
- Check the documentation at `/api.php`
- Review code examples in the dashboard
- Check server error logs for details

## 🎉 Benefits

✅ Easy integration with any platform  
✅ No complex SDKs required  
✅ Beautiful admin dashboard  
✅ Automatic Google Drive storage  
✅ Public download links  
✅ Auto-expiring files  
✅ Usage analytics  
✅ Rate limiting protection  

---

**Powered by OneNetly © 2026**
