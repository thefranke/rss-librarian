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

    // Modify settings below after a first run in the following file rather than in source
    $g_config_file = 'rsslibrarian-config.json';

    // Set to true if extracted content should be added to feed
    $g_extract_content = true;

    // Maximum length of feed
    $g_max_items = 25;

    // Set to true to store feeds as RSS 2.0, false as Atom
    $g_use_rss_format = true;

    // Directory of feed files
    $g_dir_feeds = 'feeds';

    // Instance administrator contact
    $g_instance_contact = '<a href="https://github.com/thefranke/rss-librarian/issues">Open a Github Issue</a>';
    
    // Base location
    $g_url_librarian = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
    $g_url_base = dirname($g_url_librarian);
    
    // RSS-Librarian logo
    $g_logo = 'https://raw.githubusercontent.com/Warhammer40kGroup/wh40k-icon/master/src/svgs/librarius-02.svg';
    $g_icon = $g_logo;

    // Custom CSS & XSLT Stylesheets
    $g_custom_xslt = '';
    $g_custom_css = '';

    // Read configuration from JSON file
    function update_configuration()
    {
        global $g_config_file, $g_extract_content, $g_max_items, $g_use_rss_format, 
               $g_dir_feeds, $g_instance_contact, $g_icon, $g_logo, 
               $g_custom_xslt, $g_custom_css;
        
        $json = @file_get_contents($g_config_file);

        // No config file found, create one
        if (empty($json))
        {
            file_put_contents($g_config_file, json_encode([
                'extract_content' => $g_extract_content,
                'max_items' => $g_max_items,
                'use_rss_format' => $g_use_rss_format,
                'dir_feeds' => $g_dir_feeds,
                'instance_contact' => $g_instance_contact,
                'icon' => $g_icon,
                'logo' => $g_logo,
                'custom_xslt' => $g_custom_xslt,
                'custom_css' => $g_custom_css,
            ], JSON_PRETTY_PRINT));
        }

        // Read back configuration
        else
        {
            $data = json_decode($json);

            if (property_exists($data, 'extract_content')) $g_extract_content = $data->extract_content;
            if (property_exists($data, 'max_items')) $g_max_items = $data->max_items;
            if (property_exists($data, 'use_rss_format')) $g_use_rss_format = $data->use_rss_format;
            if (property_exists($data, 'dir_feeds')) $dir_feeds = $data->dir_feeds;
            if (property_exists($data, 'instance_contact')) $instance_contact = $data->instance_contact;
            if (property_exists($data, 'icon')) $g_icon = $data->icon;
            if (property_exists($data, 'logo')) $g_logo = $data->logo;
            if (property_exists($data, 'custom_xslt')) $g_custom_xslt = $data->custom_xslt;
            if (property_exists($data, 'custom_css')) $g_custom_css = $data->custom_css;
        }
    }

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
        if (empty($s))
            return '';

        $s = trim($s);

        // Remove control characters
        $s = preg_replace('/(?>[\x00-\x1F]|\xC2[\x80-\x9F]|\xE2[\x80-\x8F]{2}|\xE2\x80[\xA4-\xA8]|\xE2\x81[\x9F-\xAF])/', ' ', $s);
        
        // Reduce all multiple whitespace to a single space
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
        global $g_url_librarian, $g_icon, $g_custom_xslt;

        return '<?xml version="1.0" encoding="utf-8"?>
            ' . (($g_custom_xslt !== "") ? '<?xml-stylesheet type="text/xsl" href="' . $g_custom_xslt . '" ?>' : '') . '
            <feed xmlns="http://www.w3.org/2005/Atom">
                <link rel="self" href="' .$feed_url . '" />
                <title>' . $title . '</title>
                <id>' . $personal_url . '</id>
                <updated>' . date('Y-m-d\TH:i:s\Z', $ts_updated) . '</updated>
                <generator uri="' . $g_url_librarian . '" version="1.0">RSS-Librarian</generator>
                <author>
                    <name>RSS-Librarian</name>
                </author>
                <icon>' . $g_icon .'</icon>
                <logo>' . $g_icon .'</logo>
            </feed>    
        ';
    }

    // Creates the base stub for an RSS feed
    function make_rss_feed($title, $subtitle, $personal_url, $feed_url, $ts_updated)
    {
        global $g_url_librarian, $g_icon, $g_custom_xslt;

        return '<?xml version="1.0" encoding="utf-8"?>
            ' . (($g_custom_xslt !== "") ? '<?xml-stylesheet type="text/xsl" href="' . $g_custom_xslt . '"?>' : '') . '
            <rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
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
        global $g_use_rss_format, $g_url_librarian;

        $title = 'RSS-Librarian (' . substr($param_id, 0, 4) . ')';
        $subtitle = 'A read-it-later service for RSS purists';
        $personal_url = get_personal_url($param_id);
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
    // https://validator.w3.org/feed/docs/atom.html
    function make_atom_item($item)
    {
        $datef = date('Y-m-d\TH:i:s\Z', $item['date']);
        
        $author_element = '';

        if (!empty($item['author']))
            $author_element = '<author><name>' . sanitize_text($item['author']) . '</name></author>';
        
        return '<entry>
            <title>' . sanitize_text($item['title']) . '</title>
            <id>' . $item['url'] .'</id>
            <published>' . $datef . '</published>
            <updated>' . $datef . '</updated>
            <content type="html">'
                . sanitize_text($item['content']) .
            '</content>'
            . $author_element .
        '</entry>';
    }

    // Creates an RSS feed entry
    // https://validator.w3.org/feed/docs/rss2.html
    // http://purl.org/dc/elements/1.1/
    function make_rss_item($item) 
    {
        $author_element = '';

        if (!empty($item['author']))
            $author_element = '<dc:creator>' . $item['author'] . '</dc:creator>';

        $title_element = '';
        if (!empty($item['title']))
        {
            $title_element = '<title>' . sanitize_text($item['title']) . '</title>';
        }

        return '<item xmlns:dc="http://purl.org/dc/elements/1.1/">
            <link>' . $item['url'] . '</link>
            ' . $title_element . '
            <guid isPermaLink="true">' . $item['url'] .'</guid>
            <description>' . sanitize_text($item['content']) . '</description>
            ' . $author_element . '
            <pubDate>' . date('D, d M Y H:i:s O', $item['date']) . '</pubDate>
        </item>';
    }

    // Create XML element for an RSS item
    function make_feed_item($item)
    {
        global $g_extract_content, $g_use_rss_format;

        if (!array_key_exists('date', $item) || $item['date'] == 0)
            $item['date'] = time();

        if (!$g_extract_content)
            $item['content'] = 'Content extraction disabled or failed, please enable reader mode for this entry.';
        else if (!array_key_exists('content', $item) || empty($item['content']))
            $item['content'] = 'Content extraction failed, please enable reader mode for this entry.';

        $item_xml_str = '';
        if ($g_use_rss_format)
            $item_xml_str = make_rss_item($item);
        else
            $item_xml_str = make_atom_item($item);

        return simplexml_load_string($item_xml_str);
    }

    // Read RSS XML item and convert to internal item format
    function read_rss_item($xml_item)
    {
        $author = '';
        
        $creator = $xml_item->xpath('dc:creator');
        
        if (empty($creator))
            sanitize_text($xml_item->author);
        else
        {
            $author = $creator[0][0];
        }

        return [
            'url' => $xml_item->guid,
            'title' => sanitize_text($xml_item->title),
            'content' => $xml_item->description,
            'date' => strtotime($xml_item->pubDate),
            'author' => $author,
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
        if (!empty($local_feed_text))
        {
            $old_feed_xml = simplexml_load_string($local_feed_text);

            // Detect old feed file format
            $is_rss = $old_feed_xml->getName() == 'rss';

            // read into array of internal items
            if ($is_rss)
            {
                $old_feed_xml->registerXPATHNamespace('dc', 'http://purl.org/dc/elements/1.1/');
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
        global $g_dir_feeds, $g_use_rss_format;

        // Check for subs dir
        if (!is_dir($g_dir_feeds))
            mkdir($g_dir_feeds);

        $feed_xml = make_feed($param_id);
        
        // Re-attach
        foreach($items as $item)
            sxml_attach($g_use_rss_format ? $feed_xml->channel : $feed_xml, make_feed_item($item));

        // Write formatted xml to feed file
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

        $item = [];

        if (file_exists($autoload))
        {
            require $autoload;

            // Pretend to be a browser to have an increased success rate of
            // downloading the contents compared to a simple `file_get_contents`.
            ini_set('user_agent','Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');

            // Fetch HTML content
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $html = curl_exec($ch);
            curl_close($ch);

            // Tidy up HTML
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

            try
            {
                $readability->parse($html);

                $item = [
                    'url' => $url,
                    'title' => $readability->getTitle(),
                    'content' => $readability->getContent(),
                    'author' => $readability->getAuthor(),
                ];
            }
            catch (ParseException $e)
            {
                // leave for second try at FiveFilters
            }
        }

        // No local Readability.php installed or extracting data failed? Use FiveFilters
        if (empty($item))
        {
            $feed_url = 'https://ftr.fivefilters.net/makefulltextfeed.php?url=' . urlencode($url);

            $feed_item = file_get_contents($feed_url);

            // error handling remove everything until first <
            $start = strpos($feed_item, '<');
            $feed_item = substr($feed_item, $start);
            $xml = simplexml_load_string($feed_item);
            $ff_item = $xml->channel->item[0];

            $item = [
                'url' => $url,
                'title' => $ff_item->title,
                'content' => $ff_item->description,
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
            write_feed_file($param_id, $items);
            return '<a href="' . $param_url . '">' . $param_url . '</a> removed';
        }
    }

    // Add URL to personal feed
    function add_url($param_id, $param_url)
    {
        global $g_max_items;

        // Turn parameter into fully qualified URL
        $parsed = parse_url($param_url);
        if (empty($parsed['scheme']))
            $param_url = 'https://' . ltrim($param_url, '/');

        $items = read_feed_file($param_id);

        // Check if item already exists
        foreach($items as $item)
        {
            if ($item['url'] == $param_url)
                return 'URL already added';
        }

        // Check max item count, remove anything beyond
        $c = count($items);
        while ($c >= $g_max_items)
        {
            unset($items[$c-1]);
            $c--;
        }

        // Fetch content and add to items
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

    // Display a list of URLs that are saved in the feed 
    // already and allow users to remove items
    function show_saved_urls($param_id)
    {
        if (empty($param_id))
            return;

        $items = read_feed_file($param_id);

        print('
        <section>
            <h2>Stored Feed Items</h2>
            <ol>');

        foreach($items as $item)
        {
            $title = (!empty($item['title'])) ? $item['title'] : $item['url'];
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
        global $g_use_rss_format, $g_extract_content, $g_max_items, $g_instance_contact, $g_custom_xslt;

        $personal_url = get_personal_url($param_id);
        $feed_url = get_feed_url($param_id);

        print('
        <section>');

        if (!empty($param_id))
        print('
            <h2>Your feed</h2>
            <p>
                Bookmark your <a href="'. $personal_url .'">personal URL</a><br> 
                Subscribe to your <a href="' . $feed_url . '">personal feed</a> with a RSS/Atom feed reader
            </p>

            <h2>Tools</h2>
            <p>
                <a href="' . ($g_custom_xslt === '' ? 'https://feedreader.xyz/?url=' . urlencode($feed_url) : $feed_url) . '">Feed preview</a>,            
                <a href="https://validator.w3.org/feed/check.cgi?url=' . urlencode($feed_url) . '">Validate feed</a>, 
                <a href="javascript:window.location.href=\'' . $personal_url . '&url=\' + window.location.href">Feed boomarklet</a>, 
                <a href="https://www.icloud.com/shortcuts/d047b96550114317beb45bb57466a88f">iOS Shortcut</a>
            </p>');
        else
        print('
            <h2>What Is This?</h2>
            <p>
                <a href="https://github.com/thefranke/rss-librarian/wiki#how-to-use">Read the wiki!</a>
            </p>
                
            <p>
                RSS-Librarian is a read-it-later service for RSS purists. Instead of creating another service 
                such as Pocket or Instapaper (together with a separate app), RSS-Librarian will let you add 
                articles you want to read to a <em>personal RSS/Atom feed</em> and they will show up in your 
                feed reader, where you can use the local functionality (for instance starring new articles) to 
                mark them for later reading. RSS-Librarian uses no database and works without accounts.
            </p>

            <h2>Get Started</h2>
            <p>
                Simply add a URL you want to read later above. Bookmark your <em>personal URL</em> 
                and subscribe to your <em>personal feed</em> with a reader app (see below). With the 
                <em>personal URL</em> you can manage and add more articles to your feed for later 
                reading.
            </p>
        ');

        print('
            <h2>Feed Readers</h2>
            <p>  
                <a href="https://capyreader.com/">Capy Reader (Android)</a>, 
                <a href="https://netnewswire.com/">NetNewsWire (iOS/MacOS)</a>, 
                <br>
                <a href="https://www.feedflow.dev/">FeedFlow (Windows/Linux)</a>,
                <a href="https://nodetics.com/feedbro/">FeedBro (Firefox/Chrome/Brave)</a>
            </p>
        
            <h2>Instance Info</h2>
            <p>
                # of hosted feeds: ' . count_feeds() . '<br>
                Full-text extraction: ' . ($g_extract_content ? 'Enabled' : 'Disabled') . '<br>
                Max items per feed: ' . $g_max_items . '<br>
                Feed format: ' . ($g_use_rss_format ? 'RSS 2.0' : 'Atom') . '<br>' .
                ((!empty($g_instance_contact)) ? 'Contact: ' . $g_instance_contact : '') . '
            </p>
        </section>');

        if (!empty($param_id))
        print('
        <section>
            <a href="' . $feed_url . '"><svg xmlns="http://www.w3.org/2000/svg" style="width: 2em" fill="currentColor" viewBox="0 0 16 16"><path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z"></path><path d="M5.5 12a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0m-3-8.5a1 1 0 0 1 1-1c5.523 0 10 4.477 10 10a1 1 0 1 1-2 0 8 8 0 0 0-8-8 1 1 0 0 1-1-1m0 4a1 1 0 0 1 1-1 6 6 0 0 1 6 6 1 1 0 1 1-2 0 4 4 0 0 0-4-4 1 1 0 0 1-1-1"></path></svg></a>
        </section>
        ');
    }

    update_configuration();

    $param_url = fetch_param('url');
    $param_id = fetch_param('id');
    $param_delete = fetch_param('delete');
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <title>RSS-Librarian</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <link rel="shortcut icon" href="<?php print($g_icon); ?>">
        <?php
        // User exists?
        if (!empty($param_id))
            print('<link rel="alternate" type="application/' . (($g_use_rss_format) ? 'rss+xml' : 'atom+xml') . '" title="RSS Librarian (' . substr($param_id, 0, 4) . ')" href="' . get_feed_url($param_id) . '">');
        ?>

        <?php if ($g_custom_css === '') { ?>
        <style>
            html {
                font-family: monospace;
                font-size: 14pt;
                color: #111;
                background-color: #eee;
                text-align: center;
            }
            input {
                display: block;
                margin: auto;
                font-size: 1.6em;
                margin-bottom: 0.8em;
            }
            input:first-child {
                width: 70%;
                border: 1px solid gray;
            }
            h1, h2, a {
                color: #17c;
            }
            h1 {
                font-size: 2em;
            }
            h2 {
                font-size: 1.6em;
            }
            h3 {
                font-size: 1.2em;
            }
            section {
                margin: auto;
                width: 50%;
                border-top: dashed 1px gray;
                padding-top: 1em;
                padding-bottom: 1em;
                text-align: center;
                overflow: auto;
            }
            section:first-child {
                text-align: center;
                border: 0px;
                padding-top: 50pt;
            }
            section:first-child img {
                border-radius: 10%;
                width: 10em;
            }
            h1, h2, h3, h4 {
                margin-top: 5pt;
                margin-bottom: 5pt;
            }
            ol, ul {
                padding: 0;
                text-align: left;
                list-style-type: numeric;
                list-style-position: inside;
            }
            li {
                width: 100%;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            img:not([src$='.svg']) {              
                border-radius: 10%;
            }
            @media (prefers-color-scheme: dark) {
                html {
                    background: #111;
                    color: #eee;
                }
                h1, h2, a {
                    color: #f93;
                }
                form, 
                img[src$='.svg'] {
                    filter: invert(1) !important;
                }
            }
            @media only screen and (min-resolution: 3dppx) {
                html {
                    font-size: 36pt;
                }
                li {
                    margin-bottom: 5pt;
                }
            }
            @media only screen and (orientation: portrait) { 
                section {
                    width: 95%;
                }
            }
        </style>
        <?php } else { ?>
        <link rel="stylesheet" type="text/css" href="<?php print($g_custom_css); ?>">
        <?php } ?>
    </head>
    <body>
        <section>
            <a href="librarian.php"><img alt="" src="<?php print($g_logo); ?>"></a>
            <h1>RSS-Librarian<?php if (!empty($param_id)) print(' (' . substr($param_id, 0, 4) . ')'); ?></h1>
            <h3>"Knoweldge is power, store it well"</h3>
            <h3>
                [<a href="https://github.com/thefranke/rss-librarian">Github</a>] 
                <?php if (!empty($param_id)) { ?>
                    [<a href="<?php print(get_personal_url($param_id)); ?>">Manage</a>] 
                    [<a href="<?php print(get_feed_url($param_id)); ?>">Subscribe</a>]
                <?php } ?>
            </h3>
        </section>
<?php
    // Adding URL for the first time, make sure user has saved their personal URLs!
    if (empty($param_id) && !empty($param_url))
    {
        // Create new user id
        $param_id = hash('sha256', random_bytes(18));

        print('
        <section>
            <h2>You are about to create a new feed</h2>
            <p>
                Please confirm that you bookmarked the two URLs in "Your Feed" below before continuing!
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
        if (!empty($param_id) && !empty($param_url))
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