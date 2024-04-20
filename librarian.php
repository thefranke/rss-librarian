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
    $url_base = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
    $url_librarian = $url_base . $_SERVER["PHP_SELF"];
    $param_url = fetch_param("url");
    $param_id = fetch_param("id");
    $dir_subs = "subscriptions";
    $local_rssfile = "";
    
    if ($param_id)
        $local_rssfile = $dir_subs . "/" . $param_id . ".xml";

    function update_rss_file()
    {
        global $local_rssfile;
        global $dir_subs;
        global $url_base;
        global $url_librarian;
        global $param_id;

        // check for subs dir
        if (!is_dir($dir_subs))
            mkdir($dir_subs);

        $personal_url = $url_librarian . '?id=' . $param_id;

        // recreate base file so changes in the header are put in with every new release
        $new_rss_base_text = '<?xml version="1.0" encoding="utf-8"?>
        <rss version="2.0">
            <channel>
                <title>RSS-Librarian</title>
                <description>A read-it-later service for RSS purists</description>
                <link>' . $personal_url . '</link>
            </channel>
        </rss>
        ';

        $rss_xml = simplexml_load_string($new_rss_base_text);

        // try to open local subscriptions and copy items over
        $local_rsstext = @file_get_contents($local_rssfile);
        if ($local_rsstext != "") 
        {
            $old_rss_xml = simplexml_load_string($local_rsstext);
            
            foreach($old_rss_xml->channel->item as $item)
                sxml_append($rss_xml->channel, $item);
        }

        return $rss_xml;
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
        global $dir_subs;

        // generate id if none is there
        $local_rsstext = "";
        if ($param_id == "")
        {
            $param_id = hash('sha256', random_bytes(18));
        }

        $local_rssfile = $dir_subs . "/" . $param_id . ".xml";
        
        $xml = update_rss_file();
        
        if ($xml->channel->item)
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
        $pub_date = date("D, d M Y H:i:s T");
        $item->addChild("pubDate", $pub_date);
        sxml_append($xml->channel, $item);

        // write to rss file
        file_put_contents($local_rssfile, $xml->asXml());
        return '<a href="' . $param_url . '">' . $param_url . '</a> added';
    }

    function count_feeds()
    {
        global $dir_subs;
        $filecount = count(glob($dir_subs . "/*.xml"));
        return $filecount;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>RSS-Librarian</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <?php
    if ($param_id != "") 
        print('<link rel="alternate" type="application/rss+xml" title="Personal feed (' . substr($param_id, 0, 4) . ')" href="' . $local_rssfile . '">');
  ?>

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
    $add_id = "";
    if ($param_id != "") 
    {
        print_r('Subscribe to <a href="' . $local_rssfile . '">personal feed</a> (' . substr($param_id, 0, 4) . '), preview <a href="https://feedreader.xyz/?url=' . urlencode($url_base . '/' . $local_rssfile) . '">it here</a><br><br>');
        $add_id = '<input type="hidden" id="id" name="id" value="' . $param_id . '">';
    }

    print_r('Paste a new URL here:<br><form action="' . $url_librarian . '"><input type="text" id="url" name="url">'.$add_id.'<br><input type="submit" value="Add to feed"></form><br><br>');
    
    if ($param_url != "")
    {
        $result = add_url();
        $personal_url = $url_librarian . '?id=' . $param_id;

        print_r($result . "<br><br>");
        print_r('Use <a href="javascript:window.location.href=\'' . $personal_url . '&url=\' + window.location.href">this boomarklet</a> to add the current open page<br><br>');
        print_r('OR<br><br>');
        print_r('Bookmark <a href="'. $personal_url .'">this URL</a> and add a URL via the input field<br><br>');
    }
?>

    </div>
</body>

</html>