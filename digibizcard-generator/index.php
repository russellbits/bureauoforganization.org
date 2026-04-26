<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check for required extensions
function checkExtensions() {
    $errors = array();
    if (!extension_loaded('gd')) {
        $errors[] = 'GD library is not installed. Please install php-gd extension.';
    }
    if (!extension_loaded('zip')) {
        $errors[] = 'ZIP extension is not installed. Please install php-zip extension.';
    }
    return $errors;
}

// Process portrait image - crop and resize to 640x630
function processPortrait($file, $targetPath) {
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception('Invalid image file');
    }
    
    $srcImage = imagecreatefromjpeg($file['tmp_name']);
    if ($srcImage === false) {
        throw new Exception('Failed to load portrait image');
    }
    
    $srcWidth = imagesx($srcImage);
    $srcHeight = imagesy($srcImage);
    
    // Target dimensions
    $targetWidth = 640;
    $targetHeight = 630;
    $targetRatio = $targetWidth / $targetHeight;
    $srcRatio = $srcWidth / $srcHeight;
    
    // Calculate crop dimensions
    if ($srcRatio > $targetRatio) {
        // Source is wider - crop width
        $cropHeight = $srcHeight;
        $cropWidth = $srcHeight * $targetRatio;
        $cropX = ($srcWidth - $cropWidth) / 2;
        $cropY = 0;
    } else {
        // Source is taller - crop height, prioritize top 30%
        $cropWidth = $srcWidth;
        $cropHeight = $srcWidth / $targetRatio;
        $cropX = 0;
        $cropY = ($srcHeight - $cropHeight) * 0.3; // Crop 30% from top for face centering
    }
    
    // Create target image
    $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
    imagecopyresampled(
        $targetImage, $srcImage,
        0, 0, $cropX, $cropY,
        $targetWidth, $targetHeight, $cropWidth, $cropHeight
    );
    
    // Save as JPEG
    imagejpeg($targetImage, $targetPath, 90);
    
    imagedestroy($srcImage);
    imagedestroy($targetImage);
}

// Process logo - validate square and resize to 300x300
function processLogo($file, $targetPath) {
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception('Invalid logo file');
    }
    
    $mimeType = $imageInfo['mime'];
    
    // Load image based on type
    if ($mimeType == 'image/png') {
        $srcImage = imagecreatefrompng($file['tmp_name']);
        $extension = 'png';
    } elseif ($mimeType == 'image/gif') {
        $srcImage = imagecreatefromgif($file['tmp_name']);
        $extension = 'gif';
    } else {
        throw new Exception('Logo must be PNG or GIF format');
    }
    
    if ($srcImage === false) {
        throw new Exception('Failed to load logo image');
    }
    
    $srcWidth = imagesx($srcImage);
    $srcHeight = imagesy($srcImage);
    
    // Validate square dimensions
    if ($srcWidth !== $srcHeight) {
        imagedestroy($srcImage);
        throw new Exception('Logo must be square (width and height must be equal). Your logo is ' . $srcWidth . 'x' . $srcHeight . ' pixels.');
    }
    
    // Resize to 300x300
    $targetSize = 300;
    $targetImage = imagecreatetruecolor($targetSize, $targetSize);
    
    // Preserve transparency
    if ($mimeType == 'image/png') {
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        imagefill($targetImage, 0, 0, $transparent);
    } elseif ($mimeType == 'image/gif') {
        $transparentIndex = imagecolortransparent($srcImage);
        if ($transparentIndex >= 0) {
            $transparentColor = imagecolorsforindex($srcImage, $transparentIndex);
            $transparentNew = imagecolorallocate($targetImage, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
            imagefill($targetImage, 0, 0, $transparentNew);
            imagecolortransparent($targetImage, $transparentNew);
        }
    }
    
    imagecopyresampled($targetImage, $srcImage, 0, 0, 0, 0, $targetSize, $targetSize, $srcWidth, $srcHeight);
    
    // Save with correct format
    $fullTargetPath = $targetPath . '.' . $extension;
    if ($mimeType == 'image/png') {
        imagepng($targetImage, $fullTargetPath);
    } else {
        imagegif($targetImage, $fullTargetPath);
    }
    
    imagedestroy($srcImage);
    imagedestroy($targetImage);
    
    return $extension;
}

// Generate HTML from template
function generateHTML($data) {
    $html = '<!doctype html>' . "\n";
    $html .= '<html lang="en">' . "\n";
    $html .= '<head>' . "\n";
    $html .= '<meta charset="utf-8" />' . "\n";
    $html .= '<link rel="icon" href="./favicon.png" />' . "\n";
    $html .= '<meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n";
    $html .= '<meta property="og:title" content="' . htmlspecialchars($data['firstName'] . ' ' . $data['lastName']) . '" />' . "\n";
    $html .= '<meta property="og:type" content="Digital Business Card" />' . "\n";
    $html .= '<meta property="og:url" content="" />' . "\n";
    $html .= '<meta property="og:image" content="./images/og-site-image.png" />' . "\n";
    $html .= '<meta name="msapplication-TileColor" content="#ffffff">' . "\n";
    $html .= '<meta name="msapplication-TileImage" content="/ms-icon-144x144.png">' . "\n";
    $html .= '<meta name="theme-color" content="#ffffff">' . "\n";
    $html .= '<link rel="apple-touch-icon" sizes="57x57" href="/apple-icon-57x57.png">' . "\n";
    $html .= '<link rel="apple-touch-icon" sizes="60x60" href="/apple-icon-60x60.png">' . "\n";
    $html .= '<link rel="apple-touch-icon" sizes="72x72" href="/apple-icon-72x72.png">' . "\n";
    $html .= '<link rel="apple-touch-icon" sizes="76x76" href="/apple-icon-76x76.png">' . "\n";
    $html .= '<link rel="apple-touch-icon" sizes="114x114" href="/apple-icon-114x114.png">' . "\n";
    $html .= '<link rel="apple-touch-icon" sizes="120x120" href="/apple-icon-120x120.png">' . "\n";
    $html .= '<link rel="apple-touch-icon" sizes="144x144" href="/apple-icon-144x144.png">' . "\n";
    $html .= '<link rel="apple-touch-icon" sizes="152x152" href="/apple-icon-152x152.png">' . "\n";
    $html .= '<link rel="apple-touch-icon" sizes="180x180" href="/apple-icon-180x180.png">' . "\n";
    $html .= '<link rel="icon" type="image/png" sizes="192x192" href="/android-icon-192x192.png">' . "\n";
    $html .= '<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">' . "\n";
    $html .= '<link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png">' . "\n";
    $html .= '<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">' . "\n";
    $html .= '<link href="./styles/card.css" rel="stylesheet">' . "\n";
    $html .= '<link href="./styles/entypo.css" rel="stylesheet">' . "\n";
    $html .= '</head>' . "\n";
    $html .= '<body>' . "\n\n";
    $html .= '<nav class="top">' . "\n";
    $html .= '<a href="#" onclick="showCard(\'id\')"><img src="images/navigation/v-card.svg" width="25" height="25" alt="Codepen logo" /></a>' . "\n";
    $html .= '<a href="#" onclick="showCard(\'qr\')"><img src="images/navigation/qr-code.svg" width="25" height="25" alt="Codepen logo" class="off" /></a>' . "\n";
    $html .= '<a href="#" onclick="showCard(\'info\')"><img src="images/navigation/info.svg" width="25" height="25" alt="Codepen logo" class="off" /></a>' . "\n";
    $html .= '</nav>' . "\n";
    $html .= '<main>' . "\n\n";
    $html .= '<div class="cardCarousel">' . "\n\n";
    $html .= '<div class="card id">' . "\n\n";
    $html .= '<figure class="portrait">' . "\n";
    $html .= '<img src="images/portrait.jpg" width="640" height="630" alt="Portrait of ' . htmlspecialchars($data['firstName'] . ' ' . $data['lastName']) . '">' . "\n";
    $html .= '</figure>' . "\n\n";
    $html .= '<div class="text">' . "\n";
    $html .= '<h1>' . htmlspecialchars($data['organization']) . '</h1>' . "\n";
    $html .= '<h2>' . htmlspecialchars($data['title']) . '</h2>' . "\n";
    $html .= '</div>' . "\n\n";
    $html .= '</div>' . "\n\n";
    $html .= '<div class="card qr">' . "\n";
    $html .= '<figure class="qr">' . "\n";
    $html .= '<img src="images/qrcode.png" alt="Business card qr code">' . "\n";
    $html .= '</figure>' . "\n";
    $html .= '</div>' . "\n\n";
    $html .= '<div class="card info">' . "\n";
    $html .= '<div class="text">' . "\n";
    $html .= '<h1>About</h1>' . "\n";
    $html .= '<h2>' . htmlspecialchars($data['note']) . '</h2>' . "\n";
    $html .= '</div>' . "\n";
    $html .= '</div>' . "\n\n";
    $html .= '</div>' . "\n\n";
    $html .= '<nav class="inline">' . "\n";
    
    // Social media buttons
    $socialLinks = array();
    if (!empty($data['linkedin'])) {
        $socialLinks[] = array('url' => 'https://' . $data['linkedin'], 'icon' => 'linkedin-white-circle.svg', 'label' => 'LinkedIn');
    }
    if (!empty($data['facebook'])) {
        $socialLinks[] = array('url' => 'https://' . $data['facebook'], 'icon' => 'facebook-white-circle.svg', 'label' => 'Facebook');
    }
    if (!empty($data['twitter'])) {
        $socialLinks[] = array('url' => 'https://' . $data['twitter'], 'icon' => 'github-white-circle.svg', 'label' => 'Twitter');
    }
    if (!empty($data['website'])) {
        $socialLinks[] = array('url' => 'https://' . $data['website'], 'icon' => 'weblink-white-circle.svg', 'label' => 'Website');
    }
    
    foreach ($socialLinks as $link) {
        $html .= '<div class="button"><a href="' . htmlspecialchars($link['url']) . '"><img src="images/navigation/' . $link['icon'] . '" width="30" height="30" alt="' . $link['label'] . ' logo" /><label>' . htmlspecialchars($link['label']) . '</label></a></div>' . "\n";
    }
    
    $html .= '</nav>' . "\n\n";
    $html .= '</main>' . "\n\n";
    $html .= '<script>' . "\n";
    $html .= 'function showCard(cardId) {' . "\n";
    $html .= 'const carousel = document.querySelector(\'.cardCarousel\');' . "\n";
    $html .= 'const cards = document.querySelectorAll(\'.card\');' . "\n";
    $html .= 'const mainWidth = document.querySelector(\'main\').offsetWidth;' . "\n";
    $html .= 'let targetIndex = 0;' . "\n";
    $html .= 'cards.forEach((card, index) => {' . "\n";
    $html .= 'if (card.classList.contains(cardId)) {' . "\n";
    $html .= 'targetIndex = index;' . "\n";
    $html .= '}' . "\n";
    $html .= '});' . "\n";
    $html .= 'const translation = (-targetIndex * mainWidth) + 3;' . "\n";
    $html .= 'carousel.style.transform = `translateX(${translation}px)`;' . "\n";
    $html .= 'document.querySelectorAll(\'nav.top img\').forEach(img => {' . "\n";
    $html .= 'img.classList.add(\'off\');' . "\n";
    $html .= '});' . "\n";
    $html .= 'document.querySelector(`nav.top a[onclick="showCard(\'${cardId}\')"] img`).classList.remove(\'off\');' . "\n";
    $html .= '}' . "\n";
    $html .= 'showCard(\'id\');' . "\n";
    $html .= '</script>' . "\n\n";
    $html .= '</body>' . "\n";
    $html .= '</html>';
    
    return $html;
}

// Generate VCF from data
function generateVCF($data) {
    $vcf = 'BEGIN:VCARD' . "\n";
    $vcf .= 'VERSION:3.0' . "\n";
    $vcf .= 'FN:' . $data['firstName'] . ' ' . $data['lastName'] . "\n";
    $vcf .= 'N:' . $data['lastName'] . ';' . $data['firstName'] . ';;;' . "\n";
    
    if (!empty($data['organization'])) {
        $vcf .= 'ORG:' . $data['organization'] . "\n";
    }
    if (!empty($data['title'])) {
        $vcf .= 'TITLE:' . $data['title'] . "\n";
    }
    if (!empty($data['email'])) {
        $vcf .= 'EMAIL;type=INTERNET;type=WORK:' . $data['email'] . "\n";
    }
    if (!empty($data['phone'])) {
        $vcf .= 'TEL;type=WORK:' . $data['phone'] . "\n";
    }
    if (!empty($data['cell'])) {
        $vcf .= 'TEL;type=CELL:' . $data['cell'] . "\n";
    }
    if (!empty($data['home'])) {
        $vcf .= 'TEL;type=HOME:' . $data['home'] . "\n";
    }
    if (!empty($data['website'])) {
        $vcf .= 'URL:https://' . $data['website'] . "\n";
    }
    if (!empty($data['note'])) {
        $vcf .= 'NOTE:' . $data['note'] . "\n";
    }
    
    $vcf .= 'END:VCARD';
    
    return $vcf;
}

// Recursively add directory to ZIP
function addDirectoryToZip($zip, $dir, $zipPath = '') {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') {
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

// Process form submission
$html = '';
$vcf = '';
$error = '';
$showPreview = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate'])) {
    try {
        // Check extensions
        $extErrors = checkExtensions();
        if (!empty($extErrors)) {
            throw new Exception(implode(' ', $extErrors));
        }
        
        // Validate required fields
        if (empty($_POST['firstName']) || empty($_POST['lastName'])) {
            throw new Exception('First name and last name are required');
        }
        
        // Validate files
        if (!isset($_FILES['portrait']) || $_FILES['portrait']['error'] != UPLOAD_ERR_OK) {
            $errorMsg = 'Portrait image is required';
            if (isset($_FILES['portrait']['error'])) {
                $errorMsg .= ' (Error code: ' . $_FILES['portrait']['error'] . ')';
            }
            throw new Exception($errorMsg);
        }
        if (!isset($_FILES['logo']) || $_FILES['logo']['error'] != UPLOAD_ERR_OK) {
            $errorMsg = 'Logo image is required';
            if (isset($_FILES['logo']['error'])) {
                $errorMsg .= ' (Error code: ' . $_FILES['logo']['error'] . ')';
            }
            throw new Exception($errorMsg);
        }
        
        // Validate portrait is actually a JPEG
        $portraitInfo = getimagesize($_FILES['portrait']['tmp_name']);
        if ($portraitInfo === false || ($portraitInfo[2] != IMAGETYPE_JPEG && $portraitInfo[2] != IMAGETYPE_JPEG2000)) {
            throw new Exception('Portrait must be a JPG/JPEG image');
        }
        
        // Create temp directory
        $tempDir = sys_get_temp_dir() . '/business_card_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/images');
        
        // Process images
        processPortrait($_FILES['portrait'], $tempDir . '/images/portrait.jpg');
        $logoExt = processLogo($_FILES['logo'], $tempDir . '/images/logo');
        
        // Prepare data
        $data = array(
            'firstName' => isset($_POST['firstName']) ? $_POST['firstName'] : '',
            'lastName' => isset($_POST['lastName']) ? $_POST['lastName'] : '',
            'organization' => isset($_POST['organization']) ? $_POST['organization'] : '',
            'title' => isset($_POST['title']) ? $_POST['title'] : '',
            'email' => isset($_POST['email']) ? $_POST['email'] : '',
            'phone' => isset($_POST['phone']) ? $_POST['phone'] : '',
            'cell' => isset($_POST['cell']) ? $_POST['cell'] : '',
            'home' => isset($_POST['home']) ? $_POST['home'] : '',
            'website' => isset($_POST['website']) ? $_POST['website'] : '',
            'facebook' => isset($_POST['facebook']) ? $_POST['facebook'] : '',
            'yelp' => isset($_POST['yelp']) ? $_POST['yelp'] : '',
            'twitter' => isset($_POST['twitter']) ? $_POST['twitter'] : '',
            'linkedin' => isset($_POST['linkedin']) ? $_POST['linkedin'] : '',
            'bluesky' => isset($_POST['bluesky']) ? $_POST['bluesky'] : '',
            'skype' => isset($_POST['skype']) ? $_POST['skype'] : '',
            'note' => isset($_POST['note']) ? $_POST['note'] : ''
        );
        
        // Generate files
        $html = generateHTML($data);
        $vcf = generateVCF($data);
        
        // Save to temp directory
        file_put_contents($tempDir . '/index.html', $html);
        $vcfFilename = $data['firstName'] . '_' . $data['lastName'] . '_vcard.vcf';
        file_put_contents($tempDir . '/' . $vcfFilename, $vcf);
        
        // Copy styles and images directories if they exist
        if (is_dir('./styles')) {
            mkdir($tempDir . '/styles');
            addDirectoryToZip(new ZipArchive(), './styles', '');
            $styleFiles = scandir('./styles');
            foreach ($styleFiles as $file) {
                if ($file != '.' && $file != '..' && is_file('./styles/' . $file)) {
                    copy('./styles/' . $file, $tempDir . '/styles/' . $file);
                }
            }
        }
        
        if (is_dir('./images')) {
            $serverImages = scandir('./images');
            foreach ($serverImages as $file) {
                if ($file != '.' && $file != '..' && is_file('./images/' . $file)) {
                    copy('./images/' . $file, $tempDir . '/images/' . $file);
                }
            }
            // Copy subdirectories
            if (is_dir('./images/navigation')) {
                mkdir($tempDir . '/images/navigation');
                $navImages = scandir('./images/navigation');
                foreach ($navImages as $file) {
                    if ($file != '.' && $file != '..' && is_file('./images/navigation/' . $file)) {
                        copy('./images/navigation/' . $file, $tempDir . '/images/navigation/' . $file);
                    }
                }
            }
        }
        
        // Store temp directory in session for download
        session_start();
        $_SESSION['temp_dir'] = $tempDir;
        $_SESSION['vcf_filename'] = $vcfFilename;
        
        $showPreview = true;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle download
if (isset($_GET['download']) && $_GET['download'] == '1') {
    session_start();
    if (isset($_SESSION['temp_dir']) && is_dir($_SESSION['temp_dir'])) {
        $tempDir = $_SESSION['temp_dir'];
        $zipFile = sys_get_temp_dir() . '/business_card_' . uniqid() . '.zip';
        
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
            addDirectoryToZip($zip, $tempDir, '');
            $zip->close();
            
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="business_card.zip"');
            header('Content-Length: ' . filesize($zipFile));
            readfile($zipFile);
            
            unlink($zipFile);
            exit;
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
        .url-input-wrapper {
            position: relative;
        }
        .url-prefix {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            pointer-events: none;
        }
        .url-input {
            padding-left: 5.5rem;
        }
        .drop-zone {
            border: 2px dashed #ccc;
            border-radius: 5px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .preview-container {
            margin-top: 2rem;
        }
        iframe {
            width: 100%;
            height: 600px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .vcf-preview {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        .error-box {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <main class="container">
        <h1>Digital Business Card Generator</h1>
        
        <?php if ($error): ?>
        <div class="error-box">
            <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            <br><br>
            <button onclick="window.location.reload()">Try Again</button>
        </div>
        <?php endif; ?>
        
        <?php if (!$showPreview): ?>
        <form method="POST" enctype="multipart/form-data">
            <h2>Images</h2>
            
            <label for="portrait">Portrait Image (JPG - will be resized to 640x630px)</label>
            <div class="drop-zone" id="portrait-zone">
                <p>Drag & drop portrait image here or click to browse</p>
                <input type="file" name="portrait" id="portrait" accept="image/jpeg,image/jpg" required style="display:none;">
            </div>
            
            <label for="logo">Logo (PNG/GIF - must be square, will be resized to 300x300px)</label>
            <div class="drop-zone" id="logo-zone">
                <p>Drag & drop logo here or click to browse</p>
                <input type="file" name="logo" id="logo" accept="image/png,image/gif" required style="display:none;">
            </div>
            
            <h2>Personal Information</h2>
            <div class="grid">
                <label>
                    First Name *
                    <input type="text" name="firstName" required>
                </label>
                <label>
                    Last Name *
                    <input type="text" name="lastName" required>
                </label>
            </div>
            
            <h2>Professional</h2>
            <label>
                Organization
                <input type="text" name="organization">
            </label>
            <label>
                Title
                <input type="text" name="title">
            </label>
            
            <h2>Contact</h2>
            <div class="grid">
                <label>
                    Email
                    <input type="email" name="email">
                </label>
                <label>
                    Phone
                    <input type="tel" name="phone">
                </label>
            </div>
            <div class="grid">
                <label>
                    Cell
                    <input type="tel" name="cell">
                </label>
                <label>
                    Home
                    <input type="tel" name="home">
                </label>
            </div>
            
            <label>Website</label>
            <div class="url-input-wrapper">
                <span class="url-prefix">https://</span>
                <input type="text" name="website" class="url-input" placeholder="example.com">
            </div>
            
            <h2>Social Media</h2>
            <label>Facebook</label>
            <div class="url-input-wrapper">
                <span class="url-prefix">https://</span>
                <input type="text" name="facebook" class="url-input" placeholder="facebook.com/yourpage">
            </div>
            
            <label>LinkedIn</label>
            <div class="url-input-wrapper">
                <span class="url-prefix">https://</span>
                <input type="text" name="linkedin" class="url-input" placeholder="linkedin.com/in/yourprofile">
            </div>
            
            <label>Twitter</label>
            <div class="url-input-wrapper">
                <span class="url-prefix">https://</span>
                <input type="text" name="twitter" class="url-input" placeholder="twitter.com/yourhandle">
            </div>
            
            <label>Bluesky</label>
            <div class="url-input-wrapper">
                <span class="url-prefix">https://</span>
                <input type="text" name="bluesky" class="url-input" placeholder="bsky.app/profile/yourhandle">
            </div>
            
            <label>Yelp</label>
            <div class="url-input-wrapper">
                <span class="url-prefix">https://</span>
                <input type="text" name="yelp" class="url-input" placeholder="yelp.com/biz/yourbusiness">
            </div>
            
            <label>Skype</label>
            <div class="url-input-wrapper">
                <span class="url-prefix">https://</span>
                <input type="text" name="skype" class="url-input" placeholder="skype.com/yourhandle">
            </div>
            
            <h2>Additional Information</h2>
            <label>
                Note
                <textarea name="note" rows="4"></textarea>
            </label>
            
            <button type="submit" name="generate">Generate Business Card</button>
        </form>
        <?php else: ?>
        <div class="preview-container">
            <h2>Preview</h2>
            
            <h3>HTML Preview</h3>
            <iframe srcdoc="<?php echo htmlspecialchars($html); ?>"></iframe>
            
            <h3>VCF Content</h3>
            <div class="vcf-preview"><?php echo htmlspecialchars($vcf); ?></div>
            
            <br>
            <a href="?download=1"><button>Download ZIP Package</button></a>
            <button onclick="window.location.reload()">Create Another</button>
        </div>
        <?php endif; ?>
    </main>
    
    <script>
        // Drag and drop functionality
        function setupDropZone(zoneId, inputId) {
            const zone = document.getElementById(zoneId);
            const input = document.getElementById(inputId);
            
            // Click to browse
            zone.addEventListener('click', function() {
                input.click();
            });
            
            // File selected via browse
            input.addEventListener('change', function() {
                if (this.files.length > 0) {
                    zone.querySelector('p').textContent = 'Selected: ' + this.files[0].name;
                }
            });
            
            // Drag over
            zone.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.add('dragover');
            });
            
            // Drag leave
            zone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('dragover');
            });
            
            // Drop
            zone.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    input.files = files;
                    zone.querySelector('p').textContent = 'Selected: ' + files[0].name;
                }
            });
        }
        
        // Initialize drop zones
        if (document.getElementById('portrait-zone')) {
            setupDropZone('portrait-zone', 'portrait');
            setupDropZone('logo-zone', 'logo');
        }
    </script>