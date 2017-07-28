# laravel_wokerman

此应用为在Laravel中使用Workerman的例子，主要实现通过PHP来通知广播，客户端接收广播消息的功能。

1.在Laravel项目中，生成一条新的命令：
```php
php artisan make:command Workerman
```
这个时候，你会发现在`app\Console\Commmands`下面新建了一个`Workerman`的php文件。
打开`Workerman.php`文件，将此代码复制进去即可。
注意：需要检查下`app\Console\Kernel.php`文件的`commands`数组里有没有`Commands\Workerman::class`添加进去，没有的话，需要手动添加一下。

2.`Help.php`中的`push_msg`方法是推送消息的代码例子。

3.`listen.js`是前端监听推送消息的代码。