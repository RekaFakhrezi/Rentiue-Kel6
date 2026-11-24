<?php
declare(strict_types=1);

$now = date('c');
$state = [
    'version' => 1, 'users' => [], 'clothing_categories' => [], 'clothings' => [],
    'rentals' => [], 'rental_items' => [], 'invoices' => [], 'payments' => [],
    'rental_returns' => [], 'inventory_logs' => [],
];
foreach ([
    ['Admin Rentique','admin@rentique.test','Admin123!','ADMIN','',''],
    ['Reka Putri','customer@rentique.test','Customer123!','CUSTOMER','0812-0000-0012','Bandung, Jawa Barat'],
] as [$name,$email,$password,$role,$phone,$address]) {
    $state['users'][] = [
        'id' => count($state['users']) + 1, 'name' => $name, 'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role,
        'phone' => $phone, 'address' => $address, 'created_at' => $now, 'updated_at' => $now,
    ];
}
$categories = [];
foreach (['Formal'=>'Busana untuk acara resmi.', 'Pesta'=>'Gaun dan busana perayaan.', 'Panggung'=>'Kostum untuk pertunjukan dan pesta tema.'] as $name=>$description) {
    $id = count($state['clothing_categories']) + 1;
    $state['clothing_categories'][] = ['id' => $id, 'name' => $name, 'description' => $description];
    $categories[$name] = $id;
}
    $products = [
        [1,'Jas Standar','SUIT','Formal','L','Hitam',100000,3,'suit',null,20000,null,null,'Jas hitam berpotongan klasik dengan dua kancing, disertai celana senada.'],
        [2,'Jas Premium','SUIT','Formal','L','Biru',250000,2,'jas-premium',null,30000,null,null,'Setelan biru maritim dengan bahu terstruktur untuk resepsi dan acara resmi.'],
        [3,'Dress Pesta','DRESS','Pesta','M','Merah',150000,2,'dress','A-Line',null,null,null,'Gaun merah bersiluet A-line dengan rok panjang yang bergerak ringan saat dikenakan.'],
        [4,'Kostum Bajak Laut','COSTUME','Panggung','L','Cokelat',80000,2,'costume',null,null,'Bajak Laut',15000,'Mantel kapten dengan kemeja beraksen ruffle dan topi untuk panggung bertema bajak laut.'],
        [5,'Slip Dress Sage','DRESS','Pesta','S','Hijau sage',145000,4,'slip-sage','Slip dress',null,null,null,'Gaun satin hijau lembut dengan tali tipis dan jatuh kain yang mengalir.'],
        [6,'Gaun Malam Onyx','DRESS','Formal','M','Hitam',185000,3,'gaun-hitam','Column',null,null,null,'Gaun hitam panjang untuk jamuan malam, dengan potongan ramping dan kilau satin.'],
        [7,'Jas Linen Pasir','SUIT','Formal','M','Beige',165000,4,'jas-linen',null,25000,null,null,'Sepasang jas dan celana linen warna pasir untuk acara siang hari.'],
        [8,'Gaun Kerajaan','COSTUME','Panggung','M','Zamrud',165000,2,'gaun-kerajaan',null,null,'Kerajaan',20000,'Gaun panggung berwarna zamrud dengan lengan panjang dan rok lebar.'],
        [9,'Dress Plisket Champagne','DRESS','Pesta','L','Champagne',175000,3,'dress-champagne','Pleated',null,null,null,'Lipatan halus berwarna champagne memberi tekstur pada gaun pesta ini.'],
        [10,'Tuksedo Arang','SUIT','Formal','XL','Arang',240000,2,'tuxedo',null,30000,null,null,'Setelan malam berwarna arang dengan kerah satin untuk acara formal.'],
        [11,'Kostum Pangeran','COSTUME','Panggung','L','Biru malam',135000,2,'pangeran',null,null,'Pangeran',15000,'Mantel seremonial brokat biru malam untuk pertunjukan berlatar kerajaan.'],
        [12,'Kostum Balerina','COSTUME','Panggung','S','Merah muda',95000,3,'balerina',null,null,'Balet',10000,'Kostum tari dengan rok tulle berlapis berwarna merah muda lembut.'],
    ];
foreach ($products as [$id,$name,$type,$cat,$size,$color,$price,$stock,$image,$style,$clean,$theme,$fee,$desc]) {
    $state['clothings'][] = [
        'id' => $id, 'name' => $name, 'type' => $type, 'category_id' => $categories[$cat],
        'size' => $size, 'color' => $color, 'rental_price_per_day' => $price, 'stock' => $stock,
        'image_url' => "/images/$image.webp", 'dress_style' => $style, 'dry_cleaning_fee' => $clean,
        'theme' => $theme, 'accessory_fee' => $fee, 'description' => $desc, 'condition' => 'Baik',
        'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
    ];
    $state['inventory_logs'][] = [
        'id' => $id, 'clothing_id' => $id, 'admin_id' => 1, 'actor_id' => 1,
        'change_type' => 'INITIAL', 'quantity_changed' => $stock,
        'notes' => 'Registrasi stok koleksi awal', 'created_at' => $now,
    ];
}
return $state;
