# Pemetaan OOP ke fitur website

Lokasi PHP di bawah berawal dari `backend/src/Models/`. Ketiga hierarki berisi 17 kelas domain. Kelas Fields membantu validasi input.

| Konsep | Kelas, metode, dan lokasi | Fitur yang dapat didemonstrasikan |
| --- | --- | --- |
| Abstraksi | User.php, Clothing.php, Payment.php | Ketiga kelas abstrak mendefinisikan kontrak pengguna, pakaian dan pembayaran. |
| Inheritance | Customer.php dan Admin.php mewarisi User | Login mengembalikan subtype sesuai role. Customer membuat sewa, Admin mengubah stok dan memproses pengembalian. |
| Inheritance dan polymorphism | Dress.php, Suit.php, Costume.php, Clothing::fromRow | Satu katalog memuat tiga subtype. RentalItem meminta biaya tambahan per unit melalui tipe induk. |
| Encapsulation | Clothing::isAvailable, reduceStock, increaseStock, setRentalPricePerDay | Form stok dan checkout mengubah properti privat lewat metode yang memeriksa jumlah dan batas stok. |
| Overriding | Customer::getRole dan Admin::getRole | Serialisasi pengguna memanggil metode yang sama dan memperoleh role subtype. |
| Polymorphism dan overriding | CashPayment.php, TransferPayment.php, EWalletPayment.php mengimplementasikan processPayment dan getPaymentMethod | Satu alur pembayaran memvalidasi uang tunai, nama bank, atau penyedia dompet sesuai pilihan. |
| Padanan di PHP | Rental.php, addItem(Clothing,int,int $qty = 1) | Parameter opsional memberi dua cara pemanggilan. PHP tidak menyediakan overload metode berdasarkan signature seperti Java. |
| Composition | Rental.php dan RentalItem.php, addItem dan book | Rental membuat dan memiliki item. Item menyimpan jumlah, durasi, tarif dan biaya tambahan. |
| Association | Customer::createRental, Rental::getCustomer | Riwayat menyaring transaksi milik pengguna. Repository memuat transaksi lama ketika halaman dibuka kembali. |
| Association | Payment.php, Invoice.php, RentalReturn.php masing-masing menerima Rental | Pembayaran, invoice, dan pengembalian merujuk transaksi yang sama. |
| Association | Clothing.php dan ClothingCategory.php | Objek pakaian menyimpan kategori dan katalog dapat difilter berdasarkan kategori. |
| Association | Admin::addClothing dan adjustStock, InventoryLog::record | Perubahan stok mencatat pakaian dan pelaku. Kegiatan sewa juga dapat memiliki pelaku pelanggan. |

Untuk menelusuri satu tindakan, ikuti formulir konfirmasi pembayaran di `app/account.php` ke controller PHP dan kelas Payment. Data akhirnya disimpan secara atomik oleh `backend/src/Storage/JsonStore.php` di `storage/data.json`, bukan database SQL.
