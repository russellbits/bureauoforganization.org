<?php

require_once __DIR__ . "/vendor/autoload.php";

use Symfony\Component\Yaml\Yaml;

$requestUri = $_SERVER["REQUEST_URI"];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = rtrim($path, "/");
$path = trim($path, "/");
$path = urldecode($path);

if (empty($path) || $path === "index.php") {
    header("Location: /");
    exit();
}

$sectionName = str_replace("_", " ", $path);
$sectionName = htmlspecialchars($sectionName);

$yamlFile = __DIR__ . "/links.yaml";
$config = Yaml::parseFile($yamlFile);

$links = [];
$found = false;

foreach ($config["categories"] as $cat) {
    if (
        strtolower($cat["name"]) === strtolower($path) ||
        strtolower($cat["name"]) === strtolower($sectionName)
    ) {
        $links = $cat["links"];
        $sectionName = $cat["name"];
        $found = true;
        break;
    }
}

if (!$found) {
    foreach ($config["categories"] as $cat) {
        $urlSlug = strtolower(preg_replace("/[^a-z0-9]+/i", "_", $cat["name"]));
        $urlSlug = trim($urlSlug, "_");
        if ($urlSlug === strtolower($path)) {
            $links = $cat["links"];
            $sectionName = $cat["name"];
            $found = true;
            break;
        }
    }
}

if (!$found) {
    http_response_code(404);
    $sectionName = "Page Not Found";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars(
        $sectionName,
    ); ?> - Bureau of Organization</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: #000066;
            border-bottom: 2px solid #000066;
            padding-bottom: 10px;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        li {
            padding: 8px 0;
            border-bottom: 1px solid #eeeeee;
        }
        li:last-child {
            border-bottom: none;
        }
        a {
            color: #0000cc;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .back-link {
            margin-bottom: 20px;
        }
        .back-link a {
            color: #666;
        }
    </style>
</head>
<body>

<div class="back-link">
    <a href="/">&laquo; Back to Bureau of Organization</a>
</div>

<div class="logo-area">
    <img src="/images/banner.jpg" alt="<?php echo htmlspecialchars(
        $site["name"],
    ); ?>">
</div>

<h1><?php echo htmlspecialchars($sectionName); ?></h1>

<?php if (!empty($links)): ?>
<ul>
<?php foreach ($links as $link): ?>
    <?php
    $url = isset($link["url"]) ? htmlspecialchars($link["url"]) : "#";
    $label = isset($link["label"]) ? htmlspecialchars($link["label"]) : "";
    ?>
    <?php if ($label && $label !== "More..."): ?>
    <li><a href="<?php echo $url; ?>"><?php echo $label; ?></a></li>
    <?php endif; ?>
<?php endforeach; ?>
</ul>
<?php elseif ($found): ?>
<p>No links in this category yet.</p>
<?php else: ?>
<p>The requested page could not be found.</p>
<?php endif; ?>

</body>
</html>
