<?php
// Set error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/php_errors.log');

// Write a test message to error log
error_log("Script started - " . date('Y-m-d H:i:s'));

// Set custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $message = date('Y-m-d H:i:s') . " - Error: [$errno] $errstr - $errfile:$errline\n";
    error_log($message);
    return false;
});

// Check for required PHP extensions
$required_extensions = array('gd', 'zip');
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        error_log("Required PHP extension not loaded: $ext");
        die("Required PHP extension not loaded: $ext. Please contact your server administrator.");
    }
}

// Check directory permissions
$upload_dir = dirname(__FILE__);
if (!is_writable($upload_dir)) {
    error_log("Directory not writable: $upload_dir");
    die("Upload directory is not writable. Please check permissions.");
}

// Debug output
$debug = array();
$debug[] = "PHP Version: " . PHP_VERSION;
$debug[] = "Error Reporting Level: " . error_reporting();
$debug[] = "Display Errors Setting: " . ini_get('display_errors');
$debug[] = "Loaded Extensions: " . implode(', ', get_loaded_extensions());

// Function to process and resize portrait image to exact dimensions
function processPortraitImage($filePath, $targetWidth, $targetHeight) {
    list($origWidth, $origHeight, $imageType) = getimagesize($filePath);
    
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($filePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($filePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($filePath);
            break;
        default:
            return base64_encode(file_get_contents($filePath));
    }
    
    $origRatio = $origWidth / $origHeight;
    $targetRatio = $targetWidth / $targetHeight;
    
    if ($origRatio > $targetRatio) {
        $newHeight = $origHeight;
        $newWidth = $origHeight * $targetRatio;
        $cropX = ($origWidth - $newWidth) / 2;
        $cropY = 0;
    } else {
        $newWidth = $origWidth;
        $newHeight = $origWidth / $targetRatio;
        $cropX = 0;
        $cropY = max(0, ($origHeight - $newHeight) * 0.3);
    }
    
    $destImage = imagecreatetruecolor($targetWidth, $targetHeight);
    imagealphablending($destImage, false);
    imagesavealpha($destImage, true);
    
    imagecopyresampled(
        $destImage,
        $sourceImage,
        0, 0,
        $cropX, $cropY,
        $targetWidth, $targetHeight,
        $newWidth, $newHeight
    );
    
    ob_start();
    imagejpeg($destImage, null, 90);
    $imageData = ob_get_clean();
    
    imagedestroy($sourceImage);
    imagedestroy($destImage);
    
    return base64_encode($imageData);
}

// Function to process and scale logo image to exact dimensions (no cropping)
function processLogoImage($filePath, $targetWidth, $targetHeight) {
    list($origWidth, $origHeight, $imageType) = getimagesize($filePath);
    
    switch ($imageType) {
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($filePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($filePath);
            break;
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($filePath);
            break;
        default:
            return base64_encode(file_get_contents($filePath));
    }
    
    $destImage = imagecreatetruecolor($targetWidth, $targetHeight);
    
    if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
        imagealphablending($destImage, false);
        imagesavealpha($destImage, true);
        $transparent = imagecolorallocatealpha($destImage, 0, 0, 0, 127);
        imagefill($destImage, 0, 0, $transparent);
    }
    
    imagecopyresampled(
        $destImage,
        $sourceImage,
        0, 0,
        0, 0,
        $targetWidth, $targetHeight,
        $origWidth, $origHeight
    );
    
    ob_start();
    if ($imageType === IMAGETYPE_PNG) {
        imagepng($destImage, null, 9);
    } elseif ($imageType === IMAGETYPE_GIF) {
        imagegif($destImage, null);
    } else {
        imagejpeg($destImage, null, 90);
    }
    $imageData = ob_get_clean();
    
    imagedestroy($sourceImage);
    imagedestroy($destImage);
    
    return base64_encode($imageData);
}

// Digital Business Card Generator
$showPreview = false;
$htmlTemplate = '';
$vcfTemplate = '';
$data = array();
$jpgImage = '';
$pngImage = '';
$jpgMime = '';
$pngMime = '';
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = array(
        'name' => array(
            'first' => isset($_POST['first_name']) ? $_POST['first_name'] : '',
            'last' => isset($_POST['last_name']) ? $_POST['last_name'] : '',
            'full' => ''
        ),
        'contact' => array(
            'email' => isset($_POST['email']) ? $_POST['email'] : '',
            'phone' => isset($_POST['phone']) ? $_POST['phone'] : '',
            'cell' => isset($_POST['cell']) ? $_POST['cell'] : '',
            'home' => isset($_POST['home']) ? $_POST['home'] : ''
        ),
        'social' => array(
            'facebook' => isset($_POST['facebook']) ? $_POST['facebook'] : '',
            'yelp' => isset($_POST['yelp']) ? $_POST['yelp'] : '',
            'twitter' => isset($_POST['twitter']) ? $_POST['twitter'] : '',
            'linkedin' => isset($_POST['linkedin']) ? $_POST['linkedin'] : '',
            'bluesky' => isset($_POST['bluesky']) ? $_POST['bluesky'] : '',
            'skype' => isset($_POST['skype']) ? $_POST['skype'] : ''
        ),
        'website' => isset($_POST['website']) ? $_POST['website'] : '',
        'note' => isset($_POST['note']) ? $_POST['note'] : '',
        'organization' => isset($_POST['organization']) ? $_POST['organization'] : '',
        'title' => isset($_POST['title']) ? $_POST['title'] : ''
    );
    
    $data['name']['full'] = trim($data['name']['first'] . ' ' . $data['name']['last']);
    
    // Handle image uploads or restore from hidden fields
    if (isset($_POST['download'])) {
        if (isset($_POST['jpg_image_data'])) {
            $jpgImage = $_POST['jpg_image_data'];
            $jpgMime = isset($_POST['jpg_mime']) ? $_POST['jpg_mime'] : 'image/jpeg';
        }
        if (isset($_POST['png_image_data'])) {
            $pngImage = $_POST['png_image_data'];
            $pngMime = isset($_POST['png_mime']) ? $_POST['png_mime'] : 'image/png';
        }
    } else {
        if (isset($_FILES['jpg_image']) && $_FILES['jpg_image']['error'] === 0) {
            $jpgImage = processPortraitImage($_FILES['jpg_image']['tmp_name'], 640, 630);
            $jpgMime = 'image/jpeg';
        }
        
        if (isset($_FILES['png_image']) && $_FILES['png_image']['error'] === 0) {
            list($logoWidth, $logoHeight) = getimagesize($_FILES['png_image']['tmp_name']);
            if ($logoWidth !== $logoHeight) {
                $errorMessage = 'Logo must be square (equal width and height). Your image is ' . $logoWidth . 'x' . $logoHeight . 'px. Please upload a square image.';
            } else {
                $pngImage = processLogoImage($_FILES['png_image']['tmp_name'], 300, 300);
                $pngMime = $_FILES['png_image']['type'];
            }
        }
    }
    
    // Only proceed if there are no errors
    if (empty($errorMessage)) {
        // Add https:// prefix to URLs if not present
        $urlFields = array('website', 'facebook', 'yelp', 'twitter', 'linkedin', 'bluesky');
        foreach ($urlFields as $field) {
            if ($field === 'website' && !empty($data[$field])) {
                if (!preg_match('/^https?:\/\//', $data[$field])) {
                    $data[$field] = 'https://' . $data[$field];
                }
            } elseif (!empty($data['social'][$field])) {
                if (!preg_match('/^https?:\/\//', $data['social'][$field])) {
                    $data['social'][$field] = 'https://' . $data['social'][$field];
                }
            }
        }
        
        // Generate HTML template
        $htmlTemplate = '<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8" />
		<link rel="icon" href="./favicon.png" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<meta property="og:title" content="' . htmlspecialchars($data['name']['full']) . '" />
		<meta property="og:type" content="Digital Business Card" />
		<meta property="og:url" content="' . htmlspecialchars($data['website']) . '" />
		<meta property="og:image" content="./images/og-site-image.png" />
		<meta name="msapplication-TileColor" content="#ffffff">
		<meta name="msapplication-TileImage" content="/ms-icon-144x144.png">
		<meta name="theme-color" content="#ffffff">
		<link rel="apple-touch-icon" sizes="57x57" href="/apple-icon-57x57.png">
		<link rel="apple-touch-icon" sizes="60x60" href="/apple-icon-60x60.png">
		<link rel="apple-touch-icon" sizes="72x72" href="/apple-icon-72x72.png">
		<link rel="apple-touch-icon" sizes="76x76" href="/apple-icon-76x76.png">
		<link rel="apple-touch-icon" sizes="114x114" href="/apple-icon-114x114.png">
		<link rel="apple-touch-icon" sizes="120x120" href="/apple-icon-120x120.png">
		<link rel="apple-touch-icon" sizes="144x144" href="/apple-icon-144x144.png">
		<link rel="apple-touch-icon" sizes="152x152" href="/apple-icon-152x152.png">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-icon-180x180.png">
		<link rel="icon" type="image/png" sizes="192x192"  href="/android-icon-192x192.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		<link href="./styles/card.css" rel="stylesheet">
		<link href="./styles/entypo.css" rel="stylesheet">
		<title>' . htmlspecialchars($data['name']['full']) . ' - Digital Business Card</title>
	</head>
	<body>

		<nav class="top">
			<a href="#" onclick="showCard(\'id\')"
					><img src="images/navigation/v-card.svg" width="25" height="25" alt="Business Card" /></a
				>
			<a href="#" onclick="showCard(\'qr\')"
					><img src="images/navigation/qr-code.svg" width="25" height="25" alt="QR Code" class="off" /></a
				>
			<a href="#" onclick="showCard(\'info\')"
					><img src="images/navigation/info.svg" width="25" height="25" alt="Info" class="off" /></a
				>
		</nav>
		<main>
			
			<div class="cardCarousel">

				<div class="card id">

					<figure class="portrait">
						<img src="images/portrait.jpg" width="640" height="630" alt="Portrait of ' . htmlspecialchars($data['name']['full']) . '">
					</figure>

					<div class="text">
						<h1>' . htmlspecialchars($data['organization']) . '</h1>
						<h2>' . htmlspecialchars($data['title']) . '</h2>
					</div>

				</div>
				
				<div class="card qr">
					<figure class="qr">
						<img src="images/qrcode.png" alt="Business card vcf qr code">
					</figure>
				</div>

				<div class="card info">
					<div class="text">
						<h1>About</h1>
						<h2>' . htmlspecialchars($data['note']) . '</h2>
					</div>
				</div>
				
			</div>
			
			<nav class="inline">';
        
        if (!empty($data['social']['linkedin'])) {
            $htmlTemplate .= '
				<div class="button"><a href="' . htmlspecialchars($data['social']['linkedin']) . '"><img src="images/navigation/linkedin-white-circle.svg" width="30" height="30" alt="Linkedin logo" /><label>LinkedIn</label></a></div>';
        }
        if (!empty($data['social']['facebook'])) {
            $htmlTemplate .= '
				<div class="button"><a href="' . htmlspecialchars($data['social']['facebook']) . '"><img src="images/navigation/facebook-white-circle.svg" width="30" height="30" alt="Facebook logo" /><label>Facebook</label></a></div>';
        }
        if (!empty($data['social']['twitter'])) {
            $htmlTemplate .= '
				<div class="button"><a href="' . htmlspecialchars($data['social']['twitter']) . '"><img src="images/navigation/instagram-white-circle.svg" width="30" height="30" alt="Twitter logo" /><label>Twitter</label></a></div>';
        }
        if (!empty($data['social']['bluesky'])) {
            $htmlTemplate .= '
				<div class="button"><a href="' . htmlspecialchars($data['social']['bluesky']) . '"><img src="images/navigation/github-white-circle.svg" width="30" height="30" alt="Bluesky logo" /><label>Bluesky</label></a></div>';
        }
        if (!empty($data['website'])) {
            $htmlTemplate .= '
				<div class="button"><a href="' . htmlspecialchars($data['website']) . '"><img src="images/navigation/weblink-white-circle.svg" width="30" height="30" alt="Website logo" /><label>Website</label></a></div>';
        }
        
        $htmlTemplate .= '
			</nav>

		</main> 
			
		<script>
			function showCard(cardId) {
				const carousel = document.querySelector(\'.cardCarousel\');
				const cards = document.querySelectorAll(\'.card\');
				const mainWidth = document.querySelector(\'main\').offsetWidth;
				
				let targetIndex = 0;
				cards.forEach((card, index) => {
					if (card.classList.contains(cardId)) {
						targetIndex = index;
					}
				});
				
				const translation = (-targetIndex * mainWidth) + 3;
				carousel.style.transform = `translateX(${translation}px)`;
				
				document.querySelectorAll(\'nav.top img\').forEach(img => {
					img.classList.add(\'off\');
				});
				document.querySelector(`nav.top a[onclick="showCard(\'${cardId}\')"] img`).classList.remove(\'off\');
			}
			
			showCard(\'id\');
		</script>
		
	</body>
</html>';
        
        // Generate VCF template
        $vcfTemplate = 'BEGIN:VCARD
VERSION:3.0
FN:' . $data['name']['full'] . '
N:' . $data['name']['last'] . ';' . $data['name']['first'] . ';;;
';
        
        if (!empty($data['organization'])) {
            $vcfTemplate .= 'ORG:' . $data['organization'] . '
';
        }
        
        if (!empty($data['title'])) {
            $vcfTemplate .= 'TITLE:' . $data['title'] . '
';
        }
        
        if (!empty($data['contact']['email'])) {
            $vcfTemplate .= 'EMAIL;type=INTERNET;type=WORK:' . $data['contact']['email'] . '
';
        }
        
        if (!empty($data['contact']['cell'])) {
            $vcfTemplate .= 'TEL;type=CELL:' . $data['contact']['cell'] . '
';
        }
        
        if (!empty($data['contact']['phone'])) {
            $vcfTemplate .= 'TEL;type=WORK:' . $data['contact']['phone'] . '
';
        }
        
        if (!empty($data['contact']['home'])) {
            $vcfTemplate .= 'TEL;type=HOME:' . $data['contact']['home'] . '
';
        }
        
        if (!empty($data['website'])) {
            $vcfTemplate .= 'URL:' . $data['website'] . '
';
        }
        
        if (!empty($data['note'])) {
            $vcfTemplate .= 'NOTE:' . str_replace("\n", "\\n", $data['note']) . '
';
        }
        
        $vcfTemplate .= 'END:VCARD';
        
        // Check if this is a download request
        if (isset($_POST['download'])) {
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                $zipFilename = 'business-card-' . time() . '.zip';
                $zipPath = sys_get_temp_dir() . '/' . $zipFilename;
                
                if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                    $vcfFilename = strtolower($data['name']['first'] . '_' . $data['name']['last']) . '_vcard.vcf';
                    $vcfFilename = preg_replace('/[^a-z0-9_]/', '', $vcfFilename);
                    
                    $zip->addFromString('index.html', $htmlTemplate);
                    $zip->addFromString($vcfFilename, $vcfTemplate);
                    
                    if ($jpgImage) {
                        $zip->addFromString('images/portrait.jpg', base64_decode($jpgImage));
                    }
                    if ($pngImage) {
                        $ext = ($pngMime === 'image/gif') ? 'gif' : 'png';
                        $zip->addFromString('images/logo.' . $ext, base64_decode($pngImage));
                    }
                    
                    function addDirectoryToZip($zip, $dir, $zipPath = '') {
                        if (!is_dir($dir)) {
                            return;
                        }
                        
                        $files = scandir($dir);
                        foreach ($files as $file) {
                            if ($file === '.' || $file === '..') {
                                continue;
                            }
                            
                            $filePath = $dir . '/' . $file;
                            $zipFilePath = $zipPath . $file;
                            
                            if (is_dir($filePath)) {
                                $zip->addEmptyDir($zipFilePath);
                                addDirectoryToZip($zip, $filePath, $zipFilePath . '/');
                            } else {
                                $zip->addFile($filePath, $zipFilePath);
                            }
                        }
                    }
                    
                    $stylesDir = __DIR__ . '/styles';
                    if (is_dir($stylesDir)) {
                        $zip->addEmptyDir('styles');
                        addDirectoryToZip($zip, $stylesDir, 'styles/');
                    }
                    
                    $imagesDir = __DIR__ . '/images';
                    if (is_dir($imagesDir)) {
                        if (!$jpgImage && !$pngImage) {
                            $zip->addEmptyDir('images');
                        }
                        addDirectoryToZip($zip, $imagesDir, 'images/');
                    }
                    
                    $zip->close();
                    
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
                    header('Content-Length: ' . filesize($zipPath));
                    readfile($zipPath);
                    unlink($zipPath);
                    exit;
                }
            } else {
                die('ZipArchive class is not available. Please enable the ZIP extension in PHP.');
            }
        } else {
            $showPreview = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Business Card Generator</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        body { padding: 2rem; }
        .container { max-width: 900px; }
        .drop-zone {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .preview-image {
            max-width: 200px;
            margin-top: 1rem;
            border-radius: 8px;
        }
        .form-group { margin-bottom: 1.5rem; }
        .header-section {
            text-align: center;
            margin-bottom: 3rem;
        }
        .header-section h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .preview-container {
            margin-top: 3rem;
            padding: 2rem;
            border: 2px solid #667eea;
            border-radius: 12px;
            background: #f8f9fa;
        }
        .preview-actions {
            text-align: center;
            margin-top: 2rem;
        }
        .url-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .url-prefix {
            position: absolute;
            left: 12px;
            color: #999;
            pointer-events: none;
            font-size: 0.95rem;
        }
        .url-input-wrapper input {
            padding-left: 65px;
        }
    </style>
</head>
<body>
    <main class="container">
        <?php if (!empty($errorMessage)): ?>
        <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
            <h3 style="color: #856404; margin-top: 0;">⚠️ Upload Error</h3>
            <p style="color: #856404; margin-bottom: 0;"><?php echo htmlspecialchars($errorMessage); ?></p>
            <a href="?" role="button" class="secondary" style="margin-top: 1rem;">← Try Again</a>
        </div>
        <?php elseif ($showPreview): ?>
        <div class="preview-container">
            <h2 style="text-align: center;">Preview Your Business Card</h2>
            <div style="background: white; padding: 2rem; border-radius: 8px; margin-bottom: 1rem;">
                <h3>HTML Preview</h3>
                <iframe srcdoc="<?php echo htmlspecialchars($htmlTemplate); ?>" style="width: 100%; height: 600px; border: none; border-radius: 8px; background: white;"></iframe>
            </div>
            
            <div style="background: white; padding: 2rem; border-radius: 8px;">
                <h3>VCF Contact Card Preview</h3>
                <pre style="background: #f4f4f4; padding: 1rem; border-radius: 4px; overflow-x: auto;"><?php echo htmlspecialchars($vcfTemplate); ?></pre>
            </div>
            
            <div class="preview-actions">
                <form method="POST" style="display: inline-block; margin-right: 1rem;">
                    <?php
                    foreach ($_POST as $key => $value) {
                        if ($key !== 'download') {
                            echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                        }
                    }
                    if ($jpgImage) {
                        echo '<input type="hidden" name="jpg_image_data" value="' . htmlspecialchars($jpgImage) . '">';
                        echo '<input type="hidden" name="jpg_mime" value="' . htmlspecialchars($jpgMime) . '">';
                    }
                    if ($pngImage) {
                        echo '<input type="hidden" name="png_image_data" value="' . htmlspecialchars($pngImage) . '">';
                        echo '<input type="hidden" name="png_mime" value="' . htmlspecialchars($pngMime) . '">';
                    }
                    ?>
                    <button type="submit" name="download" value="1">📦 Download ZIP (HTML + VCF)</button>
                </form>
                <a href="?" role="button" class="secondary">← Start Over</a>
            </div>
        </div>
        <?php else: ?>
        <div class="header-section">
            <h1>✨ Digital Business Card Generator</h1>
            <p>Create your professional business card in seconds</p>
        </div>
        
        <form method="POST" enctype="multipart/form-data" id="cardForm">
            <section>
                <h2>Images</h2>
                <div class="grid">
                    <div>
                        <label>Profile Image (JPG)</label>
                        <small style="color: #666; display: block; margin-bottom: 0.5rem;">Image will be automatically resized to 640x630px</small>
                        <div class="drop-zone" id="jpgDropZone">
                            <p>📷 Drag & drop JPG image here or click to upload</p>
                            <input type="file" name="jpg_image" id="jpgInput" accept=".jpg,.jpeg" style="display:none;">
                            <img id="jpgPreview" class="preview-image" style="display:none;">
                        </div>
                    </div>
                    <div>
                        <label>Logo (PNG/GIF)</label>
                        <small style="color: #666; display: block; margin-bottom: 0.5rem;">Must be square. Will be scaled to 300x300px</small>
                        <div class="drop-zone" id="pngDropZone">
                            <p>🖼️ Drag & drop PNG/GIF image here or click to upload</p>
                            <input type="file" name="png_image" id="pngInput" accept=".png,.gif" style="display:none;">
                            <img id="pngPreview" class="preview-image" style="display:none;">
                        </div>
                    </div>
                </div>
            </section>
            
            <section>
                <h2>Name</h2>
                <div class="grid">
                    <input type="text" name="first_name" placeholder="First Name" required>
                    <input type="text" name="last_name" placeholder="Last Name" required>
                </div>
            </section>
            
            <section>
                <h2>Professional Information</h2>
                <input type="text" name="organization" placeholder="Organization / Company">
                <input type="text" name="title" placeholder="Job Title / Position">
            </section>
            
            <section>
                <h2>Contact Information</h2>
                <div class="grid">
                    <input type="email" name="email" placeholder="Email Address">
                    <input type="tel" name="phone" placeholder="Phone Number">
                </div>
                <div class="grid">
                    <input type="tel" name="cell" placeholder="Cell Phone">
                    <input type="tel" name="home" placeholder="Home Phone">
                </div>
                <div class="url-input-wrapper">
                    <span class="url-prefix">https://</span>
                    <input type="text" name="website" placeholder="yourwebsite.com">
                </div>
            </section>
            
            <section>
                <h2>Social Media</h2>
                <div class="grid">
                    <div class="url-input-wrapper">
                        <span class="url-prefix">https://</span>
                        <input type="text" name="facebook" placeholder="facebook.com/yourpage">
                    </div>
                    <div class="url-input-wrapper">
                        <span class="url-prefix">https://</span>
                        <input type="text" name="yelp" placeholder="yelp.com/biz/yourbiz">
                    </div>
                </div>
                <div class="grid">
                    <div class="url-input-wrapper">
                        <span class="url-prefix">https://</span>
                        <input type="text" name="twitter" placeholder="twitter.com/yourhandle">
                    </div>
                    <div class="url-input-wrapper">
                        <span class="url-prefix">https://</span>
                        <input type="text" name="linkedin" placeholder="linkedin.com/in/yourprofile">
                    </div>
                </div>
                <div class="grid">
                    <div class="url-input-wrapper">
                        <span class="url-prefix">https://</span>
                        <input type="text" name="bluesky" placeholder="bsky.app/profile/yourhandle">
                    </div>
                    <input type="text" name="skype" placeholder="Skype Username">
                </div>
            </section>
            
            <section>
                <h2>Additional Note</h2>
                <textarea name="note" placeholder="Add a personal note or tagline..." rows="4"></textarea>
            </section>
            
            <button type="submit" name="generate" value="1">🚀 Generate Business Card</button>
        </form>
        <?php endif; ?>
    </main>
    
    <script>
        function setupDropZone(dropZoneId, inputId, previewId) {
            const dropZone = document.getElementById(dropZoneId);
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            
            dropZone.addEventListener('click', function() { 
                input.click(); 
            });
            
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
            
            dropZone.addEventListener('dragleave', function() {
                dropZone.classList.remove('dragover');
            });
            
            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    input.files = files;
                    displayPreview(files[0], preview, dropZone);
                }
            });
            
            input.addEventListener('change', function(e) {
                if (e.target.files.length > 0) {
                    displayPreview(e.target.files[0], preview, dropZone);
                }
            });
        }
        
        function displayPreview(file, previewElement, dropZone) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewElement.src = e.target.result;
                previewElement.style.display = 'block';
                dropZone.querySelector('p').style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
        
        setupDropZone('jpgDropZone', 'jpgInput', 'jpgPreview');
        setupDropZone('pngDropZone', 'pngInput', 'pngPreview');
    </script>
</body>
</html>