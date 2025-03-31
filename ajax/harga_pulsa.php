<?php
//
//SC	:	KM Panel - SMM & PPOB
//Dev	:	BlackRose / Adi Gunawan, S.Kom., M.Kom.
//By	:	401XD Group Indonesia
//Email	:	mycoding@401xd.com / 401xdssh@gmail.com
//
//▪ http://401xd.com / http://mycoding.net
//▪ http://youtube.com/c/MyCodingXD
//▪ http://instagram.com/MyCodingXD
//▪ http://facebook.com/MyCodingXD
//
//Hak cipta 2017-2021
//Terakhir dikembangkan Februari 2021
//Dilarang menjual/membagikan script KM Panel. Serta mengubah/menghapus copyright ini!
//

session_start();
require_once '../config.php';

if (!isset($_SESSION['user'])) {
	die("Anda Tidak Memiliki Akses!");
}

if (isset($_POST['layanan'])) {
	$post_layanan = $conn->real_escape_string($_POST['layanan']);
	$cek_layanan = $conn->query("SELECT * FROM layanan_pulsa WHERE provider_id = '$post_layanan' AND provider = 'ARIEPULSA' AND status = 'Normal'");
	if (mysqli_num_rows($cek_layanan) == 1) {
		$data_layanan = mysqli_fetch_assoc($cek_layanan);
		$hasil = $data_layanan['harga'];
		echo $hasil;
	} else {
		die("0");
	}
} else {
	die("0");
}