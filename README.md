# RSS-Librarian

RSS-Librarian is a read-it-later service for RSS purists. Instead of creating another service such as Pocket or Instapaper (together with a separate app), RSS-Librarian will let you add articles you want to read to a *personal RSS feed* and they will show up in your RSS reader, where you can use the local functionality (for instance starring new articles) to mark them for later reading. RSS-Librarian uses no database and works without accounts.

Sample instance hosted here:
https://alternator.hstn.me/librarian.php
(**important note**: if your reader cannot deal with self-signed certificates, use http instead!)

The project was born out of [a personal frustration of mine](https://github.com/Ranchero-Software/NetNewsWire/issues/3023): My workflow for reading anything I am interested in is by adding a star in my RSS reader to an article, which necessitates that anything I want to read is somehow a subscription I can add to my RSS reader, and that isn't true for single articles I get sent by someone.

RSS-Librarian solves this issue with a self-hostable PHP file by extracting content from articles using [a readability service](https://www.fivefilters.org/) and directly writing them as new entries into a *personal RSS feed*, without requiring special libraries, a database or user accounts.

# Use cases

You want to:
* Store single articles in a RSS reader application
* Avoid third-party read-it-later services such as [Pocket](https://getpocket.com), [Instapaper](https://www.instapaper.com) or [Wallabag](https://wallabag.org/)
* Minimize the amount of necessary apps for reading articles
* Get rid of accounts and not sign up to anything
* Read articles (offline) in a readable format, but not categorize or store them indefinitely
* Synchronize stored articles to multiple devices
* Optionally be able to self-host the whole architecture

# How to use

Go to your librarian instance ([try here](https://alternator.hstn.me/librarian.php)) and simply add your first URL.

On the next page you will see **two important things**:
1. Your *personal URL*: Store this URL in your bookmarks! This allows you to add more articles to your *personal RSS feed*.
2. Your *personal RSS feed*: This is your feed that you can subscribe to with your RSS reader application. It is unique and can only be managed with the *personal URL* above.

**It is important** - after adding your first link - to bookmark your *personal URL* somewhere so you can keep adding links to your feed (instead of creating a new one accidentally)!

# How it works

You can drop `librarian.php` onto any host that supports PHP with no other requirements. RSS-Librarian has two parameters: `librarian.php?id=HASH&url=SOMEPAGE`.

- `id` is a random ID for a personal feed. If this parameter is not supplied, RSS-Librarian will generate a new one and add a feed file corresponding to `id` into the subfolder `feeds/`.
- `url` is a URL you submit to RSS-Librarian, whose content will be extracted and added to the feed coressponding to `id`.

For each `url` posted to RSS-Librarian, the extracted content will be added to a RSS file derived from `id` - if it exists in the `feeds/` folder. If it does not exist, it will be generated and written. The RSS file will store a maximum of 100 entries before removing the oldest one and adding the new one. If you want articles cached for a long time, either manually increase `max_items` or configure your favorite reader to cache downloaded entries longer.