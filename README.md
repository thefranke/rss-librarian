# RSS-Librarian

RSS-Librarian is a read-it-later service for RSS purists. Instead of creating a separate service such as Pocket or Instagram (with a separate app), RSS-Librarian will allow you to add articles you want to read to a personal RSS subscription and they will show up in your RSS reader, where you can use the local functionality (for instance starring new articles) to mark them for later reading. RSS-Librarian uses no database and works without accounts.

Sample instance hosted here:
https://alternator.hstn.me/librarian.php
(**important note**: if your reader cannot deal with self-signed certificates, use http instead!)

The project was born out of [a personal frustration of mine](https://github.com/Ranchero-Software/NetNewsWire/issues/3023): My workflow for reading anything I am interested in is by adding a star in my RSS reader to an article, which necessitates that anything I want to read is somehow a subscription I can add to my RSS reader, and that isn't true for single articles I get sent by someone.

Before switching to the most excellent [NetNewsWire](https://github.com/Ranchero-Software/NetNewsWire) reader, I was fond of [Reeder](https://reederapp.com/), which includes the functionality to add read-it-later services as accounts. Essentially, Reeder was my one-stop reading application, indifferent to whether the article came from a single URL I wanted to read or from any of my subscriptions. NetNewsWire on the other hand (like most RSS readers) is a minimalist, pure RSS reader, which means anything you want to *star* inside the application has to come from an RSS feed you can subscribe to.

Luckily, both [Pocket](https://getpocket.com) and [Wallabag](https://wallabag.org/) allow you to subscribe to your personal collection of articles via RSS, but this means they become a huge dump after a while because you are not really interessted in either Pocket or Wallabag, only in redirecting saved articles to your RSS reader.

RSS-Librarian solves this issue with a self-hostable PHP file that extracts content from URLs using [a readability service](https://www.fivefilters.org/) and directly writing them as new entries into a personal RSS feed, without requiring special libraries, a database or user accounts.

# Use cases

You want to:
* Store single articles in your reader application
* Avoid third-party read-it-later services such as [Pocket](https://getpocket.com), [Instapaper](https://www.instapaper.com) or [Wallabag](https://wallabag.org/)
* Minimize your necessary apps for reading
* Get rid of accounts
* Read articles in a readable format, but not categorize or store them indefinitely

# How to use

Go to your librarian instance ([try here](https://alternator.hstn.me/librarian.php)) and simply add your first URL.

On the next page you will see two things:
1. Your personal RSS feed (generated automatically as a random hash)
2. 3 ways how to add new links to your personal feed: A parameterized URL, a bookmarklet that lets you just add the current page open in your browser, and a personal link that lets you add URLs via the Librarian's interface.

**It is important** - after adding your first link - to store this personal hash of yours somewhere so you can keep adding links to your feed (instead of creating a new one accidentally).

# How it works

You can drop `librarian.php` onto any host that supports PHP with no other requirements. RSS-Librarian has two parameters: `librarian.php?id=HASH&url=SOMEPAGE`.

- `id` is a random ID for a personal feed. If this parameter is not supplied, RSS-Librarian will generate a new one and add a file into subfolder `subscriptions/`.
- `url` is a URL you submit to RSS-Librarian, whose content will be extracted and added to the feed supplied by `id`.

For each `url` posted to RSS-Librarian, the extracted content will be pre-pended to a RSS file given by `id` if it exists in the `subscriptions/` folder. If it does not exist, it will be generated and written. The RSS file will store a maximum of 100 entries before removing the oldest one and adding the new one. If you want articles cached for a long time, either manually increase `max_items` or configure your favorite reader to cache downloaded entries longer.