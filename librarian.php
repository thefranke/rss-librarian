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



    /** Configuration **/

    // Set to true if extracted content should be added to feed
    $g_extract_content = true;

    // Maximum length of feed
    $g_max_items = 100;

    // Set to true if feeds are RSS 2.0, false if feeds are ATOM format
    $g_use_rss_format = true;

    // Directory of feed files
    $g_dir_feeds = "feeds";

    
    
    /** Code **/

    // Base location
    $g_url_librarian = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
    $g_url_base = dirname($g_url_librarian);
    
    // RSS-Librarian logo
    $g_icon = "https://raw.githubusercontent.com/Warhammer40kGroup/wh40k-icon/master/src/svgs/librarius-02.svg";

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

    // Produce path for local feed file
    function get_local_feed_file($param_id)
    {
        global $g_dir_feeds;
        return $g_dir_feeds . "/" . $param_id . ".xml";
    }

    // Produce URL for user feed
    function get_feed_url($param_id)
    {
        global $g_url_base;
        return $g_url_base . "/" . get_local_feed_file($param_id);
    }

    // Check if feed file exists
    function feed_file_exists($param_id)
    {
        return file_exists(get_local_feed_file($param_id));
    }

    // Helper to attach new XML element to existing one
    function sxml_attach(SimpleXMLElement $to, SimpleXMLElement $from)
    {
        $toDom = dom_import_simplexml($to);
        $fromDom = dom_import_simplexml($from);
        $new_node = $toDom->ownerDocument->importNode($fromDom, true);
        $firstSibling = $toDom->getElementsByTagName('item')->item(0);
        $toDom->insertBefore($new_node, $firstSibling);
    }

    function make_atom_feed($title, $subtitle, $personal_url, $feed_url, $ts_updated)
    {
        global $g_url_librarian;
        global $g_icon;

        return '<?xml version="1.0" encoding="utf-8"?>
            <feed xmlns="http://www.w3.org/2005/Atom">
                
                <title>' . $title . '</title>
                <link ref="self" href="' . $feed_url . '/>
                <link href="' . $personal_url . '" />
                
                <updated>' . date("", $ts_updated) . '</updated>
                <generator uri="' . $g_url_librarian . '" version="1.0">
                    RSS-Librarian
                </generator>
                <icon>' . $g_icon .'</icon>
                <logo>' . $g_icon .'</logo>
            </feed>    
        ';
    }

    function make_rss_feed($title, $subtitle, $personal_url, $feed_url, $ts_updated)
    {
        global $g_url_librarian;
        global $g_icon;

        return '<?xml version="1.0" encoding="utf-8"?>
            <rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
                <channel>
                    <title>' . $title . '</title>
                    <description>' . $subtitle . '</description>
                    <link>' . $personal_url . '</link>
                </channel>
            </rss>
        ';
    }

    function make_atom_entry($url, $title, $author, $content, $pub_date)  
    {
        return '<entry>
            <link href="' . $url . '" />
            <title>' . htmlspecialchars($title) . '</title>
            <id>' . $url .'</id>
            <published>' . $pub_date . '</pubDate>
            <updated>' . $pub_date . '</updated>
            <description>'
                . htmlspecialchars($content) .
            '</description>'
            . (($author != "") ? ('<author><name>' . htmlspecialchars($author) . '</name></author>') : '') . '
        </entry>';
    }

    function make_rss_item($url, $title, $author, $content, $pub_date) 
    {
        return '<item>
            <link>' . $url . '</link>
            <title>' . htmlspecialchars($title) . '</title>
            <guid isPermaLink="true">' . $url .'</guid>
            <description>'
                . htmlspecialchars($content) .
            '</description>'
            . (($author != "") ? ('<author>' . htmlspecialchars($author) . '</author>') : '') .
            '<pubDate>' . $pub_date . '</pubDate>
        </item>';
    }

    // Create XML element for an RSS item
    function make_feed_item($url, $title, $author, $content)
    {
        global $g_extract_content;

        $pub_date = date("D, d M Y H:i:s T");

        if ($title == "")
            $title = $url;

        if (!$g_extract_content || is_null($content))
            $content = "Content extraction disabled, please enable reader mode for this feed.";

        $xmlstr = make_rss_item($url, $title, $author, $content, $pub_date);

        return new SimpleXMLElement($xmlstr);
    }

    // Read and update feed files with new header
    function read_feed_file($param_id)
    {
        global $g_dir_feeds;
        global $g_url_librarian;

        // check for subs dir
        if (!is_dir($g_dir_feeds))
            mkdir($g_dir_feeds);

        // recreate base file so changes in the header are put in with every new release
        $title = 'RSS-Librarian (' . substr($param_id, 0, 4) . ')';
        $subtitle = 'A read-it-later service for RSS purists';
        $personal_url = $g_url_librarian . '?id=' . $param_id;
        $feed_url = get_feed_url($param_id);
        $ts_updated = time();
        
        $feed_xml_str = make_rss_feed($title, $subtitle, $personal_url, $feed_url, $ts_updated);

        $feed_xml = simplexml_load_string($feed_xml_str);
        $local_feed_file = get_local_feed_file($param_id);

        // try to open local subscriptions and copy items over
        $local_feed_text = @file_get_contents($local_feed_file);
        if ($local_feed_text != "")
        {
            $old_feed_xml = simplexml_load_string($local_feed_text);

            // sort by date newest to oldest
            $rss_sorted = array();
            foreach ($old_feed_xml->channel->item as $item)
                $rss_sorted[] = $item;

            usort($rss_sorted, function($a, $b) {
                return strtotime($a->pubDate) - strtotime($b->pubDate);
            });

            // re-attach
            foreach($rss_sorted as $item)
                sxml_attach($feed_xml->channel, $item);
        }

        return $feed_xml;
    }

    // Write XML data to feed file
    function write_feed_file($param_id, $rss_xml)
    {
        // write to rss file
        $local_feed_file = get_local_feed_file($param_id);
        file_put_contents($local_feed_file, $rss_xml->asXml());
    }

    // Extract content by piping through Readability.php
    function extract_readability($url)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';

        $html = "";

        if (file_exists($autoload))
        {
            require $autoload;
            // Pretend to be a browser to have an increased success rate of
            // downloading the contents compared to a simple `file_get_contents`.
            // See https://stackoverflow.com/a/11680776.
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
            $html = curl_exec($ch);
            curl_close($ch);
        }

        // No local Readability.php installed, use FiveFilters
        if ($html == "")
        {
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
            'originalURL' => $url,
        ]));

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
            $item = make_feed_item($url, $url, $url, "Content extraction failed, please enable reader mode for this feed. " . $e->getMessage());
        }

        return $item;
    }

    // Remove URL from personal feed
    function remove_url($param_id, $param_url)
    {
        $rss_xml = read_feed_file($param_id);

        $i = 0;
        $found = false;
        foreach($rss_xml->channel->item as $item)
        {
            if ($item->guid == $param_url)
            {
                unset($rss_xml->channel->item[$i]);
                $found = true;
                break;
            }
            $i++;
        }

        if ($found)
        {
            // write to rss file
            write_feed_file($param_id, $rss_xml);
            return '<a href="' . $param_url . '">' . $param_url . '</a> removed';
        }
    }

    // Add URL to personal feed
    function add_url($param_id, $param_url)
    {
        global $g_max_items;

        // turn parameter into fully qualified URL
        $parsed = parse_url($param_url);
        if (empty($parsed['scheme']))
            $param_url = 'https://' . ltrim($param_url, '/');

        $rss_xml = read_feed_file($param_id);

        // check if item already exists
        if ($rss_xml->channel->item)
        {
            foreach($rss_xml->channel->item as $item)
            {
                if ($item->link == $param_url)
                    return "URL already added";
            }
        }

        // fetch rss content and add
        $item = extract_readability($param_url);
        sxml_attach($rss_xml->channel, $item);

        // check max item count, remove anything beyond
        $c = $rss_xml->channel->item->count();
        while ($c > $g_max_items)
        {
            unset($rss_xml->channel->item[$c-1]);
            $c--;
        }

        write_feed_file($param_id, $rss_xml);
        return '<a href="' . $param_url . '">' . $param_url . '</a> added';
    }

    // Count number of feeds in feed directory
    function count_feeds()
    {
        global $g_dir_feeds;
        $filecount = count(glob($g_dir_feeds . "/*.xml"));
        return $filecount;
    }

    // Fetch personal URL string
    function get_personal_url($param_id)
    {
        global $g_url_librarian;
        return $g_url_librarian . '?id=' . $param_id;
    }

    function show_saved_urls($param_id)
    {
        if ($param_id == "")
            return;

        $rss_xml = read_feed_file($param_id);

        print('
        <section>
            <h2>Feed Items</h2>
            <ol>');

        foreach($rss_xml->channel->item as $item)
        {
            $title = $item->title != "" ? $item->title : $item->guid;
            print('
                <li><a href="?id=' .$param_id. '&delete=1&url=' .urlencode($item->guid). '" onclick="return confirm(\'Delete?\')">&#10060;</a> <a href="' .$item->guid. '" target="_blank">' . $title . '</a></li>');
        }

        print('
            </ol>
        </section>');
    }

    // Print message with tools for RSS feed management and instance information
    function show_footer($param_id)
    {
        global $g_extract_content;
        global $g_max_items;

        $personal_url = get_personal_url($param_id);
        $feed_url = get_feed_url($param_id);

        print('
        <section>');

        if ($param_id != "")
        print('
            <h2>Your feed</h2>
            <p>
                Your <a href="'. $personal_url .'">personal URL</a> and 
                <a href="' . $feed_url . '">personal RSS feed</a>
            </p>

            <h2>Your tools</h2>
            <p>
                <a href="javascript:window.location.href=\'' . $personal_url . '&url=\' + window.location.href">Feed boomarklet</a>, 
                <a href="https://feedreader.xyz/?url=' . urlencode($feed_url) . '">Feed preview</a>, 
                <a href="https://validator.w3.org/feed/check.cgi?url=' . urlencode($feed_url) . '">Validate feed</a>
            </p>');
    
        print('
            <h2>Readers</h2>
            <p>  
                <a href="https://capyreader.com/">Capy Reader (Android)</a>, 
                <a href="https://netnewswire.com/">NetNewsWire (iOS/MacOS)</a>, 
                <br>
                <a href="https://www.feedflow.dev/">FeedFlow (Windows/Linux)</a>,
                <a href="https://nodetics.com/feedbro/">FeedBro (Firefox/Chrome/Brave)</a>
            </p>
        
            <h2>Instance Info</h2>
            <p>
                # of hosted feeds: ' .count_feeds() . '<br>
                Full-text extraction: ' . ($g_extract_content ? "Enabled" : "Disabled") . '<br>
                Max items per feed: ' . $g_max_items . '
            </p>
        </section>
        ');
    }

    $param_url = fetch_param("url");
    $param_id = fetch_param("id");
    $param_delete = fetch_param("delete");
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <title>RSS-Librarian</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <link rel="shortcut icon" href="<?php echo $g_icon; ?>">
        <?php
        // User exists?
        if ($param_id != "")
            print('<link rel="alternate" type="application/rss+xml" title="RSS Librarian (' . substr($param_id, 0, 4) . ')" href="' . get_feed_url($param_id) . '">');
        ?>

        <style>
            html {
                font-family: monospace;
                font-size: 12pt;
                color: #66397C;
                background-color: #fff;
                text-align: center;   
            }
            body {
                margin: auto;
                max-width: 50%;
            }
            input {
                display: block;
                margin: auto;
                font-size: 20pt;
                margin-bottom: 12pt;
            }
            input:first-child {
                width: 70%;
                border: 1px solid gray;
            }
            a {
                color: #66397C;
            }
            h1 {
                font-size: 24pt;
            }
            h2 {
                font-size: 20pt;
            }
            h3 {
                font-size: 14pt;
            }
            section {
                border-top: dashed 1px gray;
                padding-top: 10pt;
                margin-bottom: 24pt;
            }
            section:first-child {
                margin-top: 40pt;
                border: 0px;
            }
            section:last-child{
                padding-bottom: 40pt;
            }
            h1, h2, h3, h4, ol, ul {
                margin: 5pt;
            }
            ol, ul {
                padding: 0;
            }
            img {
                width: 120pt;
            }
            @counter-style pad-3 {
                system: numeric;
                symbols: "0" "1" "2" "3" "4" "5" "6" "7" "8" "9";
                pad: 3 "0";
            }
            ol, ul {
                text-align: left;
                list-style-type: pad-3;
                list-style-position: inside;
            }
            li {
                width: 100%;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            @media (prefers-color-scheme: dark) {
                html {
                    filter: invert(1) !important;
                }
            }
            @media only screen and (min-resolution: 3dppx) {
                html {
                    height: 100vh;
                    zoom: 250%;
                }
                li {
                    margin-bottom: 5pt;
                }
            }
            @media only screen and (orientation: portrait) { 
                body {
                    max-width: 95%;
                }
            }
        </style>
    </head>
    <body>
        <section>
            <img alt="" src="<?php echo $g_icon; ?>">
            <h1>RSS-Librarian</h1>
            <h3>[<a href="https://github.com/thefranke/rss-librarian">Github</a>]</h3>
        </section>
<?php        
    // Adding URL for the first time, make sure user has saved their personal URLs!
    if ($param_id == "" && $param_url != "")
    {
        // Create new user id
        $param_id = hash('sha256', random_bytes(18));

        print('
        <section>
            <h2>You are about to create a new feed</h2>
            <p>
                Please confirm that you bookmarked the two URLs in "Your feed" below before continuing!
            </p>

            <form action="' . $g_url_librarian . '">
                <input type="hidden" id="url" name="url" value="' . $param_url . '">
                <input type="hidden" id="id"  name="id" value="' . $param_id . '">
                <input type="submit" value="Confirm">
            </form>
        </section>
        ');
    }

    // Returning user view
    else
    {
        print('
        <section>
            <h2>Add a new URL to your feed</h2>
            <form action="' . $g_url_librarian . '">
                <input type="text" id="url" name="url" placeholder="https://some-url/example.html">
                <input type="hidden" id="id" name="id" value="' . $param_id . '">
                <input type="submit" value="Add to feed">
            </form>');

        // Add or remove URL
        if ($param_id != "" && $param_url != "")
        {
            $result = "";

            if ($param_delete == "1")
                $result = remove_url($param_id, $param_url);
            else
                $result = add_url($param_id, $param_url); 

            print('
            <p>' . $result . "</p>");
        }

        print('
        </section>');

        show_saved_urls($param_id);
    }
    
    show_footer($param_id);
?>

    </body>
</html>