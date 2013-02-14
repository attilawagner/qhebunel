For the developers
------------------
Please use the following naming conventions:
 * PHP classes are CamelCased (note that the fist character is in UPPERCASE)
 * PHP functions are using_underscores()
 * PHP variables are $using_underscores
 * JavaScript functions, variables are camelCased (note that the fist character is in lowercase)
 * CSS classes and IDs are using-hyphen
 * HTML names, classes and IDs are using-hyphen
 * Note that wp_nonce_field() is treated as PHP only, meaning that the parameters are using_underscores

Please use the following coding conventions:
 * Use $wpdb->prepare() if you're dealing with variables
 * Use $wpdb->get_results() only for multiple result rows
 * Use $wpdb->get_row() for single result rows
 * Use $wpdb->get_var() for single values
 * Use ARRAY_A with $wpdb->get_results() and $wpdb->get_row()
 * Format your database queries like this:

```php
$badge_list = $wpdb->get_results(
    $wpdb->prepare(
        'select `b`.*, count(`l`.`uid`) as `users`
        from `qheb_badges` as `b`
          left join `qheb_user_badge_links` as `l`
            on (`l`.`bid`=`b`.`bid`)
        where `b`.`bgid`=%d
        group by `b`.`bid`
        order by `b`.`name`;',
        $badge_list_id
    ),
    ARRAY_A
);
```