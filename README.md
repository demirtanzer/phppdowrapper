# PHP PDO Wrapper

Basit ama iş gören bir **PHP PDO veritabanı sınıfı**.  
PDO ile SQL sorguları yazmaktan çok da uzaklaşmadan, temel CRUD işlemlerini kolaylaştırır.

---

## Özellikler

Bu sınıf ile yapabilecek temel şeyler:

✔️ Veritabanına bağlanma  
✔️ Dinamik **SELECT**, **INSERT**, **UPDATE**, **DELETE** işlemleri  
✔️ Bind (bağlama) parametreleri ile güvenli sorgular  
✔️ Upsert desteği (`ON DUPLICATE KEY UPDATE`)  
✔️ Hataları yakalama ve geri bildirim  
✔️ PDO tabanlı (MySQL + diğer PDO destekli sürücülerle uyumlu)

---

## Kurulum

Bu repo’yu klonla ya da kendi projenize dahil edin:

```bash
git clone https://github.com/demirtanzer/phppdowrapper.git
```
class.db.php dosyasını projenize require/require_once ile dahil edin.
### Kullanım
Aşağıdaki örnekler, bu sınıfı nasıl kullanabileceğinizi gösterir:
📌 1. Bağlantı Kurma
```php
require_once "class.db.php";

// MySQL için örnek
$db = new DB(
    "localhost",    // host
    "veritabani",   // database adı
    "utf8mb4",      // charset (json vb. uyumlu karakter seti)
    "kullanici",    // kullanıcı adı
    "sifre"         // şifre
);
```
📌 2. Kayıt Ekleme
```Php
$data = [
    "isim" => "Tanzer",
    "email" => "demirtanzer@gmail.com",
];

$result = $db->insert("users", $data);
```
📌 3. Veri Çekme (SELECT)
```Php
$rows = $db->select("users", "id > :id", ["id" => 0]);
```
📌 4. Kayıt Güncelleme
```Php
$db->update(
    "users",
    ["email" => "demirtanzer@gmail.com"],
    "id = :id",
    ["id" => 1]
);
```
📌 5. Kayıt Silme
```Php
$db->delete("users", "id = :id", ["id" => 2]);
```
📌 6. Upsert (Varsa Güncelle, Yoksa Ekle)
```Php
$db->upsert("users", [
    "id" => 3,
    "isim" => "Ayşe",
    "email" => "ayse@example.com"
], "id");
```
### Hata Yönetimi
Hata aldığınızda, PDOException veya kendi debug sisteminiz üzerinden detaylı bilgi alabilirsiniz.
İsterseniz kendi error callback fonksiyonunu da ayarlayabilirsiniz:
```Php
$db->setErrorCallbackFunction("print_r", "text");
```
### Lisans
Bu proje GPL-3.0 License ile lisanslanmıştır.
### Katkı
Katkı sağlamak isterseniz,
Issue alabilirsiniz 
Pull request gönderebilirsiniz 
Teşekkürler 🙌

---
