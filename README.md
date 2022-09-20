## Laravel Batch Updates
There's always been a missing bit when you want don't want to 'upsert', which Laravel covers, 
and you don't want to 'insert'. When you just need to 'update' all the rows, each having their
own varying data set, then this package will help you out.
```
## Installation

You can install the package via composer:

```bash
composer require jhavenz/laravel-batch-update
```

## Usage

### Batch updates for Eloquent models
Note: This package has been used a bit in my projects and is working nicely so far, but it's still in an infant
and I have to write the test suite. Please lmk if you see an issue or have an idea to add.
I'm aware that I'm bypassing the bindings as I'm building the query, but since I'm generally using this
logic when I wan to map 100s of rows, then fire the query off, I've hit limitations against the database
where there's 'too many bindings' for the db engine to handle.
Thoughts?..
It's better like this, right?
_Unless, of course, you're passing user input to your db... in that case, I can't imagine you'd be batch
updating while the user is waiting anyway._

---

Update multiple rows, each having their own values..and you only want to update:
(not updateOrCreate, upsert, findOrCreate, etc.)
e.g.
```php
use Jhavenz\LaravelBatchUpdate\BatchedQuery;

// 
(new BatchedQuery(User::class))->update(
    values: [
        [
            'user_id' => 123,
            'name' => 'john doe',
            'email' => 'john@example.com',
        ],
        [
            'user_id' => 234,
            'name' => 'jane doe',
            'email' => 'jane@example.com',
        ],
        // ... a whole bunch of mapped data (in memory), then 1 query gets executed
    ],
    index: <string> /** Give an index here, or leave null and the Model's key will be used  */
    quoted: <bool> /** have you quoted the value, or should I ?..   */
);
```
## Testing

```bash
#TODO - composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jonathan Havens](https://github.com/jhavenz)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
