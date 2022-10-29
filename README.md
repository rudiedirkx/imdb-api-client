# imdb-user-api

Fake API client for IMDB **USER** data.

Scrapes IMDB for your USER data, like ratings, watchlist, etc.
Since IMDB doesn't have a USER API, and doesn't like robots, you can't log in with this
package, so you need to log in with a real browser, copy 2 cookie values, and use that
for auth:

```php
$client = new Client(new AuthSession("Cookie 'at-main'", "Cookie 'ubid-main'"));
```

And then you do a 'login' (but not really) and session check:

```php
$loggedIn = $client->logIn(); // bool
```

And then you can fetch your USER data:

```php
$client->getLists(); // ListMeta[]

$client->rateTitle('tt1234567', 8); // bool

$client->addTitleToWatchlist('tt1234567'); // bool

$client->removeTitleFromWatchlist('tt1234567'); // bool

$client->titleInWatchlist('tt1234567'); // bool
```
