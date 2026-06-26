# PHP PDO Wrapper

Basit bir **PHP PDO veritabanı sınıfı**. PDO'dan uzaklaşmadan temel CRUD işlemlerini kolaylaştırır.

## Özellikler

- PDO tabanlı MySQL bağlantısı
- `select`, `insert`, `update`, `delete` yardımcı metodları
- Bind parametreleri ile değer bağlama
- MySQL `ON DUPLICATE KEY UPDATE` tabanlı `upsert` desteği
- Tablo ve kolon adları için identifier doğrulama/quote işlemi
- Hata callback'i ile HTML veya text hata çıktısı
- `_OFFLINE` sabiti ile hata callback'ini geçici olarak susturma

## Kurulum

Bu repo'yu klonlayın ya da `class.db.php` dosyasını projenize dahil edin:

```bash
git clone https://github.com/demirtanzer/phppdowrapper.git
```

## Kullanım

### Bağlantı Kurma

```php
require_once 'class.db.php';

$db = new db(
    'localhost',
    'veritabani',
    'utf8mb4',
    'kullanici',
    'sifre'
);
```

### Kayıt Ekleme

`insert()` başarılı olursa `lastInsertId()` değerini, başarısız olursa `false` döndürür.

```php
$data = [
    'isim' => 'Tanzer',
    'email' => 'demirtanzer@gmail.com',
];

$id = $db->insert('users', $data);
```

### Veri Çekme

```php
$rows = $db->select('users', 'id > :id', ['id' => 0]);
```

`WHERE` koşulunu array olarak verirseniz kolon adı quote edilir ve değer otomatik bind edilir:

```php
$rows = $db->select('users', ['id' => 1]);
$rows = $db->select('users', ['age >' => 18]);
$rows = $db->select('users', ['id' => [1, 2, 3]]);
```

Kolon listesinin güvenli quote edilmesi için `$fields` parametresini array olarak verebilirsiniz:

```php
$rows = $db->select('users', 'id = :id', ['id' => 1], ['id', 'isim', 'email']);
```

### Kayıt Güncelleme

```php
$db->update(
    'users',
    ['email' => 'demirtanzer@gmail.com'],
    ['id' => 1]
);
```

### Kayıt Silme

```php
$db->delete('users', ['id' => 2]);
```

### Upsert

`upsert()` MySQL `ON DUPLICATE KEY UPDATE` kullanır. Başarılı olursa eklenen veya güncellenen kaydın ID değerini, başarısız olursa `false` döndürür.

```php
$id = $db->upsert('users', [
    'id' => 3,
    'isim' => 'Ayse',
    'email' => 'ayse@example.com',
], 'id');
```

### Raw SQL Çalıştırma

`run()` doğrudan SQL çalıştırmak içindir. Bu metod SQL'i değiştirmez; sorguyu sizin yazdığınız haliyle `prepare()` ve `execute()` üzerinden çalıştırır.

```php
$rows = $db->run('SELECT * FROM users WHERE id = :id', ['id' => 1]);
```

`run()` `select`, `describe`, `pragma` ve `show` sorgularında kayıtları array olarak döndürür. `insert`, `update` ve `delete` sorgularında etkilenen satır sayısını döndürür.

## Hata Yönetimi

Hata callback'i tanımlamak için:

```php
$db->setErrorCallbackFunction('print_r', 'text');
```

HTML formatı için:

```php
$db->setErrorCallbackFunction('print_r', 'html');
```

Hata callback'ini geçici olarak susturmak için `_OFFLINE` sabitini `true` tanımlayabilirsiniz:

```php
define('_OFFLINE', true);
```

## Güvenlik Notu

Bu wrapper değerleri bind parametreleri ile bağlar. CRUD metodlarında tablo ve kolon adları identifier olarak doğrulanıp quote edilir. `select`, `update` ve `delete` metodlarında `WHERE` için array kullanırsanız kolonlar quote edilir ve değerler otomatik bind edilir.

String `WHERE` ifadesi ve raw `run()` sorguları ise geliştiricinin verdiği SQL parçalarıdır. Direkt SQL çalıştırabilmek için bu davranış korunur. Bu parçaları kullanıcı girdisinden doğrudan üretmeyin; dinamik değerler için mutlaka bind parametreleri kullanın.

Geçerli tablo/kolon adları harf veya `_` ile başlamalı, devamında harf, rakam veya `_` içermelidir. `schema.table` biçimi desteklenir. Örnek:

```php
$db->select('public.users', 'id = :id', ['id' => 1], ['id', 'email']);
```

## Repo Sürümüne Göre Değişiklikler

- ERP'ye özel `logTableNameMatches`, `ensureLogsSchema` ve `logPreparePayload` hook'ları kaldırıldı.
- `_OFFLINE` kontrolü değişken yerine sabit olarak düzeltildi.
- HTML hata çıktısı repodaki `error.css` dosyasını kullanacak şekilde düzeltildi.
- CRUD metodlarında tablo/kolon adları doğrulanıp quote edilir.
- `select()`, `update()` ve `delete()` metodları güvenli array `WHERE` formatını destekler.
- `select()` kolon listesi array verilirse kolon adlarını güvenli şekilde quote eder.
- `insert()` ve `upsert()` başarılı işlemde `lastInsertId()` döndürür.
- `run()` `SHOW` sorgularını sonuç döndüren sorgu olarak destekler.
- `run()` hata yakalamada `Throwable` kullanır ve `prepare()` başarısızlığında `false` döndürür.
- Bağlantı hatasında kullanıcı adı, şifre veya bağlantı bilgileri ekrana yazdırılmaz.

## Lisans

Bu proje GPL-3.0 License ile lisanslanmıştır.

## Katkı

Issue açabilir veya pull request gönderebilirsiniz.
