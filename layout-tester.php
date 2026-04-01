<?php
$default_url = isset($_SERVER['HTTP_HOST']) ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '';
$test_url = isset($_POST['test_url']) ? filter_var($_POST['test_url'], FILTER_SANITIZE_URL) : $default_url;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Layout Tester</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: sans-serif; margin: 30px; }
        iframe { margin: 10px 10px 20px 0; background: #fff; }
        form { margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Layout Tester</h1>
    <form method="post" action="">
        <label for="test_url"><strong>URL to test:</strong></label>
        <input type="url" id="test_url" name="test_url" value="<?php echo htmlspecialchars($test_url); ?>" style="width:350px;" placeholder="Enter URL to test" required />
        <button type="submit">Update All iFrames</button>
    </form>
    <p>Enter the URL you want to test in various device sizes.</p>
    <hr>
    <h2>Phone Sizes (Portrait)</h2>
    <iframe src="<?php echo htmlspecialchars($test_url); ?>" width="320" height="480" style="border:1px solid #ccc;"></iframe>
    <iframe src="<?php echo htmlspecialchars($test_url); ?>" width="360" height="640" style="border:1px solid #ccc;"></iframe>
    <iframe src="<?php echo htmlspecialchars($test_url); ?>" width="375" height="667" style="border:1px solid #ccc;"></iframe>
    <iframe src="<?php echo htmlspecialchars($test_url); ?>" width="414" height="736" style="border:1px solid #ccc;"></iframe>
    <h2>Tablet Sizes</h2>
    <iframe src="<?php echo htmlspecialchars($test_url); ?>" width="600" height="960" style="border:1px solid #ccc;"></iframe>
    <iframe src="<?php echo htmlspecialchars($test_url); ?>" width="768" height="1024" style="border:1px solid #ccc;"></iframe>
    <iframe src="<?php echo htmlspecialchars($test_url); ?>" width="1024" height="768" style="border:1px solid #ccc;"></iframe>
    <h2>Desktop Sizes</h2>
    <iframe src="<?php echo htmlspecialchars($test_url); ?>" width="1280" height="800" style="border:1px solid #ccc;"></iframe>
    <iframe src="<?php echo htmlspecialchars($test_url); ?>" width="1440" height="900" style="border:1px solid #ccc;"></iframe>
    <iframe src="<?php echo htmlspecialchars($test_url); ?>" width="1920" height="1080" style="border:1px solid #ccc;"></iframe>
</body>
</html>
