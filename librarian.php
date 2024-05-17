<?php
    /*
     * RSS-Librarian - A read-it-later service for RSS purists
     * 
     * https://github.com/thefranke/rss-librarian
     *
     */

    use fivefilters\Readability\Readability;
    use fivefilters\Readability\Configuration;
    use fivefilters\Readability\ParseException;

    // Global configuration
    $g_max_items = 100;
    $g_url_base = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
    $g_url_librarian = $g_url_base . $_SERVER["PHP_SELF"];
    $g_dir_feeds = "feeds";
    
    // Fetch parameters given to librarian
    function fetch_param($param)
    {
        $params = $_GET;
        
        foreach($params as $k => $v)
        {
            if ($k == $param)
                return $v;
        }

        return "";
    }

    // Turn user ID into feed ID
    function get_feed_id($user_id)
    {
        return $user_id;
        
        // Later feature: Disconnect feed from user
        //return hash('sha256', $user_id);
    }

    // Produce path for local feed file
    function get_local_feed_file($param_id)
    {
        global $g_dir_feeds;
        $file_hash = get_feed_id($param_id);
        return $g_dir_feeds . "/" . $file_hash . ".xml";
    }

    // Update feed files with new header
    function update_feed_file($user_id)
    {
        global $g_dir_feeds;
        global $g_url_librarian;

        // check for subs dir
        if (!is_dir($g_dir_feeds))
            mkdir($g_dir_feeds);

        $personal_url = $g_url_librarian . '?id=' . $user_id;

        // recreate base file so changes in the header are put in with every new release
        $new_rss_base_text = '<?xml version="1.0" encoding="utf-8"?>
        <rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
            <channel>
                <title>RSS-Librarian (' . substr($user_id, 0, 4) . ')</title>
                <description>A read-it-later service for RSS purists</description>
                <link>' . $personal_url . '</link>
                <atom:link href="' . $personal_url . '" rel="self" type="application/rss+xml" />
            </channel>
        </rss>
        ';

        $rss_xml = simplexml_load_string($new_rss_base_text);
        $local_feed_file = get_local_feed_file($user_id);

        // try to open local subscriptions and copy items over
        $local_feed_text = @file_get_contents($local_feed_file);
        if ($local_feed_text != "") 
        {
            $old_rss_xml = simplexml_load_string($local_feed_text);
            
            foreach($old_rss_xml->channel->item as $item)
                sxml_append($rss_xml->channel, $item);
        }

        return $rss_xml;
    }

    // Helper to attach new XML element to existing one
    function sxml_append(SimpleXMLElement $to, SimpleXMLElement $from) 
    {
        $toDom = dom_import_simplexml($to);
        $fromDom = dom_import_simplexml($from);
        $new_node = $toDom->ownerDocument->importNode($fromDom, true);
        $firstSibling = $toDom->getElementsByTagName('item')->item(0);
        $toDom->insertBefore($new_node, $firstSibling);
    }
    
    // Create XML element for an RSS item
    function make_feed_item($url, $title, $author, $content)
    {
        $pub_date = date("D, d M Y H:i:s T");

        $xmlstr = '<item>
            <link>' . $url . '</link>
            <title>' . $title . '</title>

            <guid isPermaLink="true">' . $url .'</guid>
            <description>'
                . htmlspecialchars($content) .
            '</description>
            <author>' . $author . '</author>
            <pubDate>' . $pub_date . '</pubDate>
        </item>';

        return new SimpleXMLElement($xmlstr);
    }

    // Extract content by piping through Readability.php
    function extract_readability($url)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';

        $html = "";

        if (file_exists($autoload))
        {
            require $autoload;
            $html = @file_get_contents($url);
        }

        // No local Readability.php installed, use FiveFilters
        if ($html == "")
        {
            echo "using ftr";
            $feed_url = "https://ftr.fivefilters.net/makefulltextfeed.php?url=" . urlencode($url);
        
            $feed_item = file_get_contents($feed_url);

            // error handling remove everything until first <
            $start = strpos($feed_item, "<");
            $feed_item = substr($feed_item, $start);
            $xml = simplexml_load_string($feed_item);
            $ff_item = $xml->channel->item[0];

            $title = $ff_item->title;
            $content = $ff_item->description;
            $author = "";
            return make_feed_item($url, $title, $author, $content);
        }

        if (function_exists('tidy_parse_string')) 
        {
            $tidy = tidy_parse_string($html, array(), 'UTF8');
            $tidy->cleanRepair();
            $html = $tidy->value;
        }

        $readability = new Readability(new Configuration([
            'fixRelativeURLs' => true,
        ]));

        $html = file_get_contents($url);
        
        $item = "";
        try
        {
            $readability->parse($html);

            $title = $readability->getTitle();
            $content = $readability->getContent();
            $author = $readability->getAuthor();
            $item = make_feed_item($url, $title, $author, $content);
        } 
        catch (ParseException $e) 
        {
            $item = make_feed_item($url, $url, $url, "Could not extract content: " . $e->getMessage());
        }
        
        return $item;
    }

    // Add URL to personal feed
    function add_url($user_id, $param_url)
    {
        global $g_max_items;

        $local_feed_file = get_local_feed_file($user_id);
        
        $xml = update_feed_file($user_id);
        
        if ($xml->channel->item)
        {
            // check max item count, remove anything beyond
            $c = $xml->channel->item->count();
            while ($c > $g_max_items)
            {
                unset($xml->channel->item[$c - 1]);
                $c--;
            }

            // check if item already exists
            foreach($xml->channel->item as $item)
            {
                if ($item->link == $param_url)
                    return "URL already added";
            }
        }

        // fetch rss content and add
        $item = extract_readability($param_url);
        sxml_append($xml->channel, $item);

        // write to rss file
        file_put_contents($local_feed_file, $xml->asXml());
        return '<a href="' . $param_url . '">' . $param_url . '</a> added';
    }

    // Count number of feeds in feed directory
    function count_feeds()
    {
        global $g_dir_feeds;
        $filecount = count(glob($g_dir_feeds . "/*.xml"));
        return $filecount;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>RSS-Librarian</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <link rel="shortcut icon" href="favicon.png">
  <?php
    $param_url = fetch_param("url");
    $param_id = fetch_param("id");
    $user_id = $param_id;

    // user exists?
    if ($param_id != "") 
        print('<link rel="alternate" type="application/rss+xml" title="RSS Librarian (' . substr($param_id, 0, 4) . ')" href="' . get_local_feed_file($param_id) . '">');
    else
        $user_id = hash('sha256', random_bytes(18));
  ?>

  <style>
    html {
      font-family: monospace;
      font-size: 12pt;
      color: #66397C;
      text-align: center;
    }
    #main {
      text-align: center;
      margin: auto;
      max-width: 75%;
      display: inline-block;
    }
    hr {
      border: 1px dashed;
    }
    a:link, a:visited {
      color: #66397C;
    }
    ul {
      margin: 10px;
    }
    #header {
        text-align: center;
        margin: 24pt;
    }
    h1, h2, h3, h4 {
        margin: 0;
    }
    img {
        width: 120pt;
    }
    @media (prefers-color-scheme: dark) {
        html {
            background-color: #000;
            color: #99c683;
        }
        a:link, a:visited {
            color: #99c683;   
        }
        img {
            filter:invert(1);
        }
    }
  </style>
</head>
<body>
    <div id="main">
        <div id="header">
            <img alt="" src="https://raw.githubusercontent.com/Warhammer40kGroup/wh40k-icon/master/src/svgs/librarius-02.svg">

            <h1>RSS-Librarian</h1>
            <h4>[<a href="https://github.com/thefranke/rss-librarian">Github</a>]</h4>
            <br>
            <hr>
            <h4>Instance managing <?php echo count_feeds() ?> feeds</h4>
        </div>
<?php
    // Check if parameter was supplied to distinguish new users from existing ones
    if ($param_id != "") 
    {
        $personal_url = $g_url_librarian . '?id=' . $user_id;
        $feed_id = get_feed_id($user_id);
        $local_feed_file = get_local_feed_file($user_id);
        print_r('<h4>Managing articles of ' . substr($user_id, 0, 4) . '...' . substr($user_id, -4) .':</h4>');
        print_r('Your <a href="'. $personal_url .'">personal URL</a> and <a href="' . $local_feed_file . '">personal RSS feed</a><br><br><br>');
    }

    print_r('<h4>Paste a new URL here:</h4>
             <form action="' . $g_url_librarian . '">
             <input type="text" id="url" name="url">
             <input type="hidden" id="id" name="id" value="' . $user_id . '">
             <br>
             <input type="submit" value="Add to feed">
             </form><br><br>');
    
    if ($param_url != "")
    {
        $result = add_url($user_id, $param_url);
        print_r($result);
    }

    if ($param_id)
    {
        print_r('<br><br><br>');
        print_r('<h4>More tools:</h4><a href="javascript:window.location.href=\'' . $personal_url . '&url=\' + window.location.href">Feed boomarklet</a>, <a href="https://feedreader.xyz/?url=' . urlencode($g_url_base . '/' . $local_feed_file) . '">Feed preview</a>');
    }
?>

    </div>
</body>

</html>