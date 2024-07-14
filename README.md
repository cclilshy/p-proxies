### 简介

该项目提供代理池服务,内部实现了 提取器、验活器、中继器
可以达到统一入站,自我验活功能,
使用者无需关心代理的存活和延迟状态,仅需设置一个代理地址即可,可以实现每次访问都使用不同的代理
目前只支持socks5的出站代理, 本人亲测性能跑满本机宽带20mb/s

开发过程仅兼顾兼容Unix系统,且Windows系统保证运行不起来qwq

### 安装

```bash
git cloen https://github.com/cclilshy/p-proxies.git 
cd p-proxies
composer install
```

### 运行方法

```bash
#启动代理池服务
php artisan app:proxies

#启动API服务
php artisan p:run
```

### 开放接口

```php
//抛入代理
Route::get('/api/push', [Api::class, 'push']);

//有效代理数量
Route::get('/api/count', [Api::class, 'count']);

//验活队列数量
Route::get('/api/queue', [Api::class, 'queue']);

//随机取一条有效代理
Route::get('/api/get', [Api::class, 'get']);
```

```bash
#插入一条代理
curl http://127.0.0.1:8008/api/push?protocol=socks5&host=127.0.0.1&port=1080
```

### 访问服务

```bash
#仅需设置一次代理
export http_proxy=http://127.0.0.1:29980
export https_proxy=https://127.0.0.1:29980

#验证代理效果
curl https://ipconfig.io/ip #得到结果1
curl https://ipconfig.io/ip #得到结果2
curl https://ipconfig.io/ip #得到结果3
curl https://ipconfig.io/ip #得到结果4
curl https://ipconfig.io/ip #得到结果5
```

### 附

> 此项目为学习项目,不保证稳定性,仅供学习参考
> 没做太多扩展如局域网过滤等功能


