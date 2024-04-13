<?php
    /*
     * RSS-Librarian - A read-it-later service for RSS purists
     * 
     * https://github.com/thefranke/rss-librarian
     *
     */

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

    $max_items = 100;
    $base_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER["PHP_SELF"];
    $param_url = fetch_param("url");
    $param_id = fetch_param("id");
    $local_rssfile = "";

    function rss_template()
    {
        return '<?xml version="1.0" encoding="utf-8"?>
        <rss version="2.0">
            <channel>
                <title>RSS-Librarian</title>
                <description>Readable bookmarks as RSS</description>
                <link>https://github.com/thefranke/rss-librarian</link>
            </channel>
        </rss>
        ';
    }

    function sxml_append(SimpleXMLElement $to, SimpleXMLElement $from) 
    {
        $toDom = dom_import_simplexml($to);
        $fromDom = dom_import_simplexml($from);
        $new_node = $toDom->ownerDocument->importNode($fromDom, true);
        $firstSibling = $toDom->getElementsByTagName('item')->item(0);
        $toDom->insertBefore($new_node, $firstSibling);
    }

    function fetch_rss_item($url)
    {
        $rssurl = "https://ftr.fivefilters.net/makefulltextfeed.php?url=" . urlencode($url);
        
        $rsstext = file_get_contents($rssurl);

        // error handling remove everything until first <
        $start = strpos($rsstext, "<");
        $rsstext = substr($rsstext, $start);
        $xml = simplexml_load_string($rsstext);
        $oneitem = $xml->channel->item[0];
        return $oneitem;
    }

    function add_url()
    {
        global $param_id;
        global $param_url;
        global $local_rssfile;
        global $max_items;

        // supplied url to rss-ify
        if ($param_url == "")
            return "No URL parameter supplied.";

        // generate id if none is there
        $local_rsstext = "";
        $is_new = false;
        if ($param_id == "")
        {
            $param_id = hash('sha256', random_bytes(18));
            $local_rsstext = rss_template();
            $is_new = true;
        }

        $subsdir = "subscriptions";
        if (!is_dir($subsdir))
            mkdir($subsdir);

        // try to open local subscriptions
        $local_rssfile = $subsdir . "/" . $param_id . ".xml";
        if ($local_rsstext == "")
        {
            $local_rsstext = @file_get_contents($local_rssfile);
            if ($local_rsstext == "") 
            {
                $local_rsstext = rss_template();
                $is_new = true;
            }
        }

        $xml = simplexml_load_string($local_rsstext);

        if (!$is_new)
        {
            // check max item count, remove anything beyond
            $c = $xml->channel->item->count();
            while ($c > $max_items)
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
        $item = fetch_rss_item($param_url);
        $pub_date= date("D, d M Y H:i:s T");
        $item->addChild("pubDate", $pub_date);
        sxml_append($xml->channel, $item);

        // write to rss file
        file_put_contents($local_rssfile, $xml->asXml());
        return $param_url . " added";
    }

    function count_feeds()
    {
        $directory = "subscriptions/";
        $filecount = count(glob($directory . "*.xml"));
        return $filecount;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>RSS-Librarian</title>
  <style>
    html {
      font-family: monospace;
      font-size: 16px;
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
        margin: 40pt;
    }
    h1, h2, h3, h4 {
        margin: 5pt;
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
            filter:invert();
        }
    }
  </style>
</head>
<body>
    <div id="main">
        <div id="header">
            <img alt="" src="https://raw.githubusercontent.com/Warhammer40kGroup/wh40k-icon/master/src/svgs/librarius-02.svg">

            <h1>RSS-Librarian</h1>
            <h3></h3>
            <h4>[<a href="https://github.com/thefranke/rss-librarian">Github</a>]</h4>
            <br>
            <hr>
            <h4>Instance managing <?php echo count_feeds() ?> feeds</h4>
        </div>
<?php
    if ($param_url == "")
    {
        $add_id = "";
        if ($param_id != "") 
        {
            print_r('Adding to personal feed ' . $param_id. '<br><br>');
            $add_id = '<input type="hidden" id="id" name="id" value="'.$param_id.'">';
        }

        print_r('Paste your URL here:<br><form action="'.$base_url.'"><input type="text" id="url" name="url">'.$add_id.'<br><input type="submit"></form>');
    }
    else
    {
        $result = add_url();
        $personal_url = $base_url . '?id=' . $param_id;

        print_r($result . "<br><br>");
        print_r('<a href="' . $local_rssfile . '">Subscribe via RSS to your personal feed here</a><br><br>');
        print_r('Add more links to your personal feed via<br>' . $personal_url. '&url=YOUR_URL_HERE<br><br>');
        print_r('OR<br><br>');
        print_r('Use <a href="javascript:window.location.href=\'' . $personal_url . '&url=\' + window.location.href">this boomarklet</a> to add the current open page<br><br>');
        print_r('OR<br><br>');
        print_R('Bookmark <a href="'. $personal_url .'">this URL</a> and add a URL via the input field');
    }
?>
    </div>
</body>

</html>