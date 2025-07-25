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
    $g_dir_feeds = 'feeds';

    
    
    /** Code **/

    // Base location
    $g_url_librarian = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
    $g_url_base = dirname($g_url_librarian);
    
    // RSS-Librarian logo
    $g_icon = 'https://raw.githubusercontent.com/Warhammer40kGroup/wh40k-icon/master/src/svgs/librarius-02.svg';

    // Fetch parameters given to librarian
    function fetch_param($param)
    {
        $params = $_GET;

        foreach($params as $k => $v)
        {
            if ($k == $param)
                return $v;
        }

        return '';
    }

    // Sanitize a string to remove any invalid characters
    function sanitize_text($s)
    {
        $s = trim($s);

        // remove control characters
        $s = preg_replace('/(?>[\x00-\x1F]|\xC2[\x80-\x9F]|\xE2[\x80-\x8F]{2}|\xE2\x80[\xA4-\xA8]|\xE2\x81[\x9F-\xAF])/', ' ', $s);
        
        // reduce all multiple whitespace to a single space
        $s = preg_replace('/\s+/', ' ', $s); 

        $s = html_entity_decode($s);
        $s = htmlspecialchars($s);

        return $s;
    }

    // Produce path for local feed file
    function get_local_feed_file($param_id)
    {
        global $g_dir_feeds;
        return $g_dir_feeds . '/' . $param_id . '.xml';
    }

    // Produce URL for user feed
    function get_feed_url($param_id)
    {
        global $g_url_base;
        return $g_url_base . '/' . get_local_feed_file($param_id);
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

    // Creates the base stub for an Atom feed
    function make_atom_feed($title, $subtitle, $personal_url, $feed_url, $ts_updated)
    {
        global $g_url_librarian;
        global $g_icon;

        return '<?xml version="1.0" encoding="utf-8"?>
            <feed xmlns="http://www.w3.org/2005/Atom">
                
                <link rel="self" href="' .$feed_url . '" />
                <title>' . $title . '</title>
                <id>' . $personal_url . '</id>
                
                <updated>' . date('Y-m-d\TH:i:s\Z', $ts_updated) . '</updated>
                <generator uri="' . $g_url_librarian . '" version="1.0">
                    RSS-Librarian
                </generator>
                <icon>' . $g_icon .'</icon>
                <logo>' . $g_icon .'</logo>
            </feed>    
        ';
    }

    // Creates the base stub for an RSS feed
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
                    <generator>RSS-Librarian</generator>
                    <atom:link href="' . $feed_url . '" rel="self" type="application/rss+xml" />
                </channel>
            </rss>
        ';
    }

    // Creates an XML element for a feed stub for the configured format (RSS or Atom)
    function make_feed($param_id)
    {
        global $g_use_rss_format;
        global $g_url_librarian;

        $title = 'RSS-Librarian (' . substr($param_id, 0, 4) . ')';
        $subtitle = 'A read-it-later service for RSS purists';
        $personal_url = $g_url_librarian . '?id=' . $param_id;
        $feed_url = get_feed_url($param_id);
        $ts_updated = time();

        $feed_xml_str = '';

        if ($g_use_rss_format)
            $feed_xml_str = make_rss_feed($title, $subtitle, $personal_url, $feed_url, $ts_updated);
        else 
            $feed_xml_str = make_atom_feed($title, $subtitle, $personal_url, $feed_url, $ts_updated);

        return simplexml_load_string($feed_xml_str);
    }

    // Creates an Atom feed entry
    function make_atom_item($url, $title, $author, $content, $date)  
    {
        return '<entry>
            <title>' . sanitize_text($title) . '</title>
            <id>' . $url .'</id>
            <published>' . date('Y-m-d\TH:i:s\Z', $date) . '</published>
            <updated>' . date('Y-m-d\TH:i:s\Z', $date) . '</updated>
            <content type="html">'
                . sanitize_text($content) .
            '</content>
            <author>
                <name>' . (($author != '') ? sanitize_text($author) : $url) . '</name>
            </author>
        </entry>';
    }

    // Creates an RSS feed entry
    function make_rss_item($url, $title, $author, $content, $date) 
    {
        // RSS requires a qualified email for a valid author name
        if (!strpos($author, '@'))
            $author = 'no@mail (' . $author . ')';

        return '<item>
            <link>' . $url . '</link>
            <title>' . sanitize_text($title) . '</title>
            <guid isPermaLink="true">' . $url .'</guid>
            <description>'
                . sanitize_text($content) .
            '</description>'
            . (($author != '') ? ('<author>' . sanitize_text($author) . '</author>') : '') .
            '<pubDate>' . date('D, d M Y H:i:s O', $date) . '</pubDate>
        </item>';
    }

    // Create XML element for an RSS item
    function make_feed_item($item)
    {
        global $g_extract_content;
        global $g_use_rss_format;

        if (!array_key_exists('date', $item) || $item['date'] == 0)
            $item['date'] = time();

        if (!array_key_exists('title', $item) || $item['title'] == '')
            $item['title'] = $item['url'];

        if (!$g_extract_content)
            $item['content'] = 'Content extraction disabled or failed, please enable reader mode for this entry.';
        else if (!array_key_exists('content', $item) || $item['content'] == '')
            $item['content'] = 'Content extraction failed, please enable reader mode for this entry.';

        $item_xml_str = '';
        if ($g_use_rss_format)
            $item_xml_str = make_rss_item($item['url'], $item['title'], $item['author'], $item['content'], $item['date']);
        else
            $item_xml_str = make_atom_item($item['url'], $item['title'], $item['author'], $item['content'], $item['date']);

        return simplexml_load_string($item_xml_str);
    }

    // Read RSS XML item and convert to internal item format
    function read_rss_item($xml_item)
    {
        return [
            'url' => $xml_item->guid,
            'title' => sanitize_text($xml_item->title),
            'content' => $xml_item->description,
            'date' => strtotime($xml_item->pubDate),
            'author' => sanitize_text($xml_item->author),
        ];
    }

    // Read RSS XML item and convert to internal item format
    function read_atom_item($xml_item)
    {
        return [
            'url' => $xml_item->id,
            'title' => sanitize_text($xml_item->title),
            'content' => $xml_item->content,
            'date' => strtotime($xml_item->published),
            'author' => sanitize_text($xml_item->author->name),
        ];
    }

    // Read feed into an array of internal feed items sorted by date
    function read_feed_file($param_id)
    {
        global $g_url_librarian;

        $local_feed_file = get_local_feed_file($param_id);

        $items_sorted = array();

        // Try to open local subscriptions and copy items over
        $local_feed_text = @file_get_contents($local_feed_file);
        if ($local_feed_text != '')
        {
            $old_feed_xml = simplexml_load_string($local_feed_text);

            // Detect old feed file format
            $is_rss = $old_feed_xml->getName() == 'rss';

            // read into array of internal items
            if ($is_rss)
            {
                foreach ($old_feed_xml->channel->item as $xml_item)
                    $items_sorted[] = read_rss_item($xml_item);
            }
            else
            {
                foreach ($old_feed_xml->entry as $xml_item)
                    $items_sorted[] = read_atom_item($xml_item);
            }

            // Sort by date newest to oldest
            usort($items_sorted, function($a, $b) {
                return $b['date'] - $a['date'];
            });
        }

        return $items_sorted;
    }

    // Write XML data to feed file
    function write_feed_file($param_id, $items)
    {
        global $g_dir_feeds;
        global $g_use_rss_format;

        // Check for subs dir
        if (!is_dir($g_dir_feeds))
            mkdir($g_dir_feeds);

        $feed_xml = make_feed($param_id);
        
        // Re-attach
        foreach($items as $item)
            sxml_attach($g_use_rss_format ? $feed_xml->channel : $feed_xml, make_feed_item($item));

        // write formatted xml to feed file
        $local_feed_file = get_local_feed_file($param_id);
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($feed_xml->asXML());
        file_put_contents($local_feed_file, $dom->saveXML());
    }

    // Extract content by piping through Readability.php and create an internal feed item
    function extract_content($url)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';

        $html = '';

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
        if ($html == '')
        {
            $feed_url = 'https://ftr.fivefilters.net/makefulltextfeed.php?url=' . urlencode($url);

            $feed_item = file_get_contents($feed_url);

            // error handling remove everything until first <
            $start = strpos($feed_item, '<');
            $feed_item = substr($feed_item, $start);
            $xml = simplexml_load_string($feed_item);
            $ff_item = $xml->channel->item[0];

            $title = $ff_item->title;
            $content = $ff_item->description;
            $author = '';

            return [
                'url' => $url,
                'title' => $title,
                'content' => $content,
                'author' => $author,
            ]; 
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

        $item = [];
        try
        {
            $readability->parse($html);

            $title = $readability->getTitle();
            $content = $readability->getContent();
            $author = $readability->getAuthor();
            $item = [
                'url' => $url,
                'title' => $title,
                'content' => $content,
                'author' => $author,
            ];
        }
        catch (ParseException $e)
        {
            $item = [
                'url' => $url,
                'title' => $url,
                'content' => 'Content extraction failed, please enable reader mode for this feed. ' . $e->getMessage(),
                'author' => '',
            ];
        }

        return $item;
    }

    // Remove URL from personal feed
    function remove_url($param_id, $param_url)
    {
        $items = read_feed_file($param_id);

        $i = 0;
        $found = false;
        foreach($items as $item)
        {
            if ($item['url'] == $param_url)
            {
                unset($items[$i]);
                $found = true;
                break;
            }
            $i++;
        }

        if ($found)
        {
            // write to rss file
            write_feed_file($param_id, $items);
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

        $items = read_feed_file($param_id);

        // check if item already exists
        foreach($items as $item)
        {
            if ($item['url'] == $param_url)
                return 'URL already added';
        }

        // check max item count, remove anything beyond
        $c = count($items);
        while ($c >= $g_max_items)
        {
            unset($items[$c-1]);
            $c--;
        }

        // fetch content and add to items
        array_unshift($items, extract_content($param_url));
        
        write_feed_file($param_id, $items);
        return '<a href="' . $param_url . '">' . $param_url . '</a> added';
    }

    // Count number of feeds in feed directory
    function count_feeds()
    {
        global $g_dir_feeds;
        $filecount = count(glob($g_dir_feeds . '/*.xml'));
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
        if ($param_id == '')
            return;

        $items = read_feed_file($param_id);

        print('
        <section>
            <h2>Feed Items</h2>
            <ol>');

        foreach($items as $item)
        {
            $title = $item['title'] != '' ? $item['title'] : $item['url'];
            print('
                <li><a href="?id=' .$param_id. '&delete=1&url=' .urlencode($item['url']). '" onclick="return confirm(\'Delete?\')">&#10060;</a> <a href="' .$item['url']. '" target="_blank">' . $title . '</a></li>');
        }

        print('
            </ol>
        </section>');
    }

    // Print message with tools for RSS feed management and instance information
    function show_footer($param_id)
    {
        global $g_use_rss_format;
        global $g_extract_content;
        global $g_max_items;

        $personal_url = get_personal_url($param_id);
        $feed_url = get_feed_url($param_id);

        print('
        <section>');

        if ($param_id != '')
        print('
            <h2>Your feed</h2>
            <p>
                Your <a href="'. $personal_url .'">personal URL</a> and 
                <a href="' . $feed_url . '">personal feed</a>
            </p>

            <h2>Your tools</h2>
            <p>
                <a href="https://feedreader.xyz/?url=' . urlencode($feed_url) . '">Feed preview</a>, 
                <a href="https://validator.w3.org/feed/check.cgi?url=' . urlencode($feed_url) . '">Validate feed</a>, 
                <a href="javascript:window.location.href=\'' . $personal_url . '&url=\' + window.location.href">Feed boomarklet</a>, 
                <a href="https://www.icloud.com/shortcuts/d047b96550114317beb45bb57466a88f">iOS Shortcut</a>
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
                Full-text extraction: ' . ($g_extract_content ? 'Enabled' : 'Disabled') . '<br>
                Max items per feed: ' . $g_max_items . '<br>
                Feed format: ' . ($g_use_rss_format ? 'RSS 2.0' : 'Atom') . '
            </p>
        </section>
        ');
    }

    $param_url = fetch_param('url');
    $param_id = fetch_param('id');
    $param_delete = fetch_param('delete');
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <title>RSS-Librarian</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <link rel="shortcut icon" href="<?php echo $g_icon; ?>">
        <?php
        // User exists?
        if ($param_id != '')
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
    if ($param_id == '' && $param_url != '')
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
        if ($param_id != '' && $param_url != '')
        {
            $result = '';

            if ($param_delete == '1')
                $result = remove_url($param_id, $param_url);
            else
                $result = add_url($param_id, $param_url); 

            print('
            <p>' . $result . '</p>');
        }

        print('
        </section>');

        show_saved_urls($param_id);
    }
    
    show_footer($param_id);
?>

    </body>
</html>