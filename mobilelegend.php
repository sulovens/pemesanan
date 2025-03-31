<?php
session_start();
require '../config.php';
require '../lib/session_user.php';
if (isset($_POST['pesan'])) {
	require '../lib/session_login.php';
	$post_operator = $conn->real_escape_string(trim(filter($_POST['operator'])));
	$post_layanan = $conn->real_escape_string(trim(filter($_POST['layanan'])));
	$post_target = $conn->real_escape_string(trim(filter($_POST['target'])));
	$post_nometer = $conn->real_escape_string(trim(filter($_POST['no_meter'])));
	$post_trxke = $conn->real_escape_string(trim(filter($_POST['trx_ke'])));

	$cek_layanan = $conn->query("SELECT * FROM layanan_pulsa WHERE provider_id = '$post_layanan' AND status = 'Normal'");
	$data_layanan = mysqli_fetch_assoc($cek_layanan);
	
	$cek_user= $conn->query("SELECT * FROM users WHERE username = '$sess_username' AND status = 'Aktif'");
	$data_user = mysqli_fetch_assoc($cek_user);
	$email = $data_user['email'];
	$nohp = $data_user['no_hp'];

	$user_id = $data_user['id'];
    $dateoid = date('Ymd'); // Format tanggal yang konsisten
	$order_id = "$post_layanan-$user_id-$dateoid-$post_target$post_nometer-$post_trxke";
	$provider = $data_layanan['provider'];

	$cek_provider = $conn->query("SELECT * FROM provider WHERE code = '$provider'");
	$data_provider = mysqli_fetch_assoc($cek_provider);
	$action = 'pemesanan';

	$cek_pesanan = $conn->query("SELECT * FROM pembelian_pulsa WHERE user = '$sess_username' AND oid = '$order_id' AND status = 'Pending' AND provider = 'ARIEPULSA'");
	$data_pesanan = mysqli_fetch_assoc($cek_pesanan);
	
	$cek_pesanan_sukses = $conn->query("SELECT * FROM pembelian_pulsa WHERE user = '$sess_username' AND oid = '$order_id' AND status = 'Success' AND provider = 'ARIEPULSA'");
	$data_pesanan_sukses = mysqli_fetch_assoc($cek_pesanan_sukses);
	
	$cek_pesanan_gagal = $conn->query("SELECT * FROM pembelian_pulsa WHERE user = '$sess_username' AND oid = '$order_id' AND status = 'Error' AND provider = 'ARIEPULSA'");
	$data_pesanan_gagal = mysqli_fetch_assoc($cek_pesanan_gagal);
	
	if (!$post_target || !$post_layanan || !$post_operator) {
		$_SESSION['hasil'] = array('alert' => 'danger', 'judul' => 'Order Gagal', 'pesan' => 'Lengkapi Bidang Berikut:<br/> - Kategori <br /> - Produk <br /> - Target');

	} else if (mysqli_num_rows($cek_layanan) == 0) {
		$_SESSION['hasil'] = array('alert' => 'danger', 'judul' => 'Order Gagal', 'pesan' => 'Produk Tidak Tersedia');

	} else if (mysqli_num_rows($cek_provider) == 0) {
		$_SESSION['hasil'] = array('alert' => 'danger', 'judul' => 'Order Gagal', 'pesan' => 'Server Sedang Maintenance');

	} else if ($data_user['saldo'] < $data_layanan['harga']) {
		$_SESSION['hasil'] = array('alert' => 'danger', 'judul' => 'Order Gagal', 'pesan' => 'Saldo Tidak Mencukupi');

	} else if (mysqli_num_rows($cek_pesanan) == 1) {
		$_SESSION['hasil'] = array('alert' => 'danger', 'judul' => 'Order Gagal', 'pesan' => 'Orderan Sebelumnya Nomor Trx & Produk & Target yang Sama Masih Pending');
		
	} else if (mysqli_num_rows($cek_pesanan_sukses) == 1) {
		$_SESSION['hasil'] = array('alert' => 'danger', 'judul' => 'Order Gagal', 'pesan' => "Orderan Trx Ke $post_trxke Sudah Pernah Dilakukan Dengan Status Sukses. Silahkan Ganti Nomor Transaksi Selanjutnya");
		
	} else if (mysqli_num_rows($cek_pesanan_gagal) == 1) {
		$_SESSION['hasil'] = array('alert' => 'danger', 'judul' => 'Order Gagal', 'pesan' => "Orderan Trx Ke $post_trxke Sudah Pernah Dilakukan Dengan Status Gagal. Silahkan Ganti Nomor Transaksi Selanjutnya");

	} else {

		if ($provider == "ARIEPULSA") {
                
			$curl = curl_init();
 
			curl_setopt_array($curl, array(
			  CURLOPT_URL => 'https://ariepulsa.com/api/pulsa',
			  CURLOPT_RETURNTRANSFER => true,
			  CURLOPT_ENCODING => '',
			  CURLOPT_MAXREDIRS => 10,
			  CURLOPT_TIMEOUT => 0,
			  CURLOPT_FOLLOWLOCATION => true,
			  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			  CURLOPT_CUSTOMREQUEST => 'POST',
			  CURLOPT_POSTFIELDS => array('api_key' => $data_provider['api_key'],'action' => 'pemesanan','layanan' => $data_layanan['provider_id'],'target' => $post_target,'no_meter' => $post_nometer),
			));
			
			$response = curl_exec($curl);
			
			curl_close($curl);
			 $json_result = json_decode($response, true);
			 
		 } 
		if ($provider == "ARIEPULSA" AND $json_result['status'] == false) {
			$_SESSION['hasil'] = array('alert' => 'danger', 'judul' => 'Order Gagal', 'pesan' => ''.$json_result['data']['pesan']);
		} else {
			if ($provider == "ARIEPULSA") {
				$provider_oid = $json_result['data']['id'];
			}
			
				if ($conn->query("INSERT INTO pembelian_pulsa VALUES ('','$order_id', '$provider_oid', '$sess_username', '$nohp', '".$data_layanan['layanan']."', '".$data_layanan['harga']."', '".$data_layanan['profit']."', '$post_target$post_nometer', '$post_nometer', '$post_trxke', '$pesan', 'Pending', '$date', '$time', 'Website', '$provider', '0')") == true) {

					$conn->query("UPDATE users SET saldo = saldo - ".$data_layanan['harga'].", pemakaian_saldo = pemakaian_saldo + ".$data_layanan['harga']." WHERE username = '$sess_username'");
					$conn->query("INSERT INTO history_saldo VALUES ('', '$sess_username', 'Pengurangan Saldo', '".$data_layanan['harga']."', 'Order ID $order_id Produk PPOB', '$date', '$time')");

					$harga = number_format($data_layanan['harga'],0,',','.');
					$_SESSION['hasil'] = array(
						'alert' => 'success',
						'judul' => 'Order Berhasil', 
						'pesan' => '<br/> 
						<b>Order ID : </b> '.$order_id.'<br />
						<b>Layanan : </b> '.$data_layanan['layanan'].'<br />
						<b>Target : </b> '.$post_target.'<br />
						<b>Harga : </b> Rp '.$harga.'');
				} else {
					$_SESSION['hasil'] = array('alert' => 'danger', 'judul' => 'Order Gagal', 'pesan' => 'Server Sedang Maintenance. Coba Lagi 15 Menit Kedepan.');
				}
			}
		}
	}


require("../lib/header.php");
?>

<!--Title-->
<title>MOBILE LEGEND</title>
<meta name="description" content="Platform Layanan Digital All in One, Berkualitas, Cepat & Aman. Menyediakan Produk & Layanan Pemasaran Sosial Media, Payment Point Online Bank, Layanan Pembayaran Elektronik, Optimalisasi Toko Online, Voucher Game dan Produk Digital."/>

<div class="row">
	<div class="col-md-7">
		<div class="card">
			<div class="card-body">
				<h4 class="text-uppercase text-center header-title"><i class="mdi mdi-cellphone-iphone mr1 text-primary"></i><b> MOBILE LEGEND</b></h4><hr>
				<form class="form-horizontal" method="POST">
					<input type="hidden" name="csrf_token" value="<?php echo $config['csrf_token'] ?>">   										    
					<div class="form-group">
						<label class="col-md-12 control-label">Kategori *</label>
						<div class="col-md-12">
							<select class="form-control" name="operator" id="operator">
								<option value="0">Pilih Salah Satu</option>
								<?php
								$cek_kategori = $conn->query("SELECT * FROM kategori_layanan WHERE kode IN ('MOBILE LEGENDS') ORDER BY nama ASC");
								while ($data_kategori = $cek_kategori->fetch_assoc()) {
									?>
									<option value="<?php echo $data_kategori['kode']; ?>"><?php echo $data_kategori['nama']; ?></option>
								<?php } ?>														
							</select>
						</div>
					</div>
					
					<div class="form-group">
						<label class="col-md-12 control-label">Produk *</label>
						<div class="col-md-12">
							<select class="form-control" name="layanan" id="layanan">
								<option value="0">Pilih Kategori</option>
							</select>
						</div>
					</div>

					<div id="catatan">
					</div>
					
					<div class="form-row">
						<div class="form-group col-md-6">
							<label class="col-md-12 col-form-label">ID Game *</label>
							<div class="col-md-12">
								<input type="number" name="target" class="form-control" placeholder="Input ID Game">
							</div>
						</div>
						
							<div class="form-group col-md-6">
							<label class="col-md-12 col-form-label">ID Zona *</label>
							<div class="col-md-12">
								<input type="number" name="no_meter" class="form-control" placeholder="Input ID Zona">
							</div>
						</div>
					</div>
					
					<div class="form-row">
						<div class="form-group col-md-6">
							<label class="col-md-12 col-form-label">Trx Ke *</label>
							<div class="col-md-12">
								<select class="form-control" type="number" name="trx_ke" required>
								    <option value="1">1</option>
									<option value="2">2</option>
									<option value="3">3</option>
									<option value="4">4</option>
									<option value="5">5</option>
									<option value="6">6</option>
									<option value="7">7</option>
									<option value="8">8</option>
									<option value="9">9</option>
									<option value="10">10</option>
							</select>
							</div>
						</div>

						<div class="form-group col-md-6">
							<label class="col-md-12 col-form-label">Total</label>
							<div class="col-md-12">
								<div class="input-group">
									<div class="input-group-prepend">
										<span class="input-group-text">Rp </span>
									</div>
									<input type="number" class="form-control" id="harga" readonly>
								</div>
							</div>
						</div>
					</div>

					<div class="form-group">
						<div class="col-md-12">
							<button type="submit" class="btn btn-block btn-primary waves-effect w-md waves-light" name="pesan" id="checkout" disabled="disabled"><i class="mdi mdi-cart"></i>Order</button>
						</div>
					</div>
				</form>

				<div style="text-align: center;">
					<br/>Dengan melakukan order, Anda telah memahami dan menyetujui <a href="#informasiorder"><b>Syarat & Ketentuan</b></a>, serta mengikuti <a href="#informasiorder"><b>Cara Melakukan Order</b></a> sesuai panduan.
				</div>
				
			</div>
		</div>
	</div>			
	<!-- end col -->

	<!-- INFORMASI ORDER -->
	<div class="col-md-5" id="informasiorder">
		<div class="card">
			<div class="card-body">

				<center><h4 class="m-t-0 text-uppercase header-title"><i aria-hidden="true" class="fa fa-info-circle"></i><b> Informasi Order</h4></b>
					Gunakan koneksi internet yang stabil agar daftar produk sinkron dengan kategori yang dipilih.<hr>
				</center>

				<!--KETENTUAN-->
				<div class="table-responsive">
					<center><i aria-hidden="true" class="fa fa-check-circle"></i><b> Syarat & Ketentuan</b></center>
					<ol class="list-p">
						<li>Mengikuti cara order di bawah.</li>
						<li>Memahami & mengikuti <a href="#catatan"><b><u>Deskripsi</u></b></a> dibawah pilihan produk.</li>
						<li>Target atau tujuan sesuai <b><a href="/halaman/target-pesanan" target="_blank">Contoh Target Pesanan</a></b>.</li>
						<li>Kesalahan input dan order pending atau processing tidak dapat di cancel atau refund.</li>
						<li>Order baru untuk produk & target yang sama, tunggu status order sebelumnya <span class="badge badge-success"><b>Success</b></span>.</li>
						<li>Memahami & mengikuti <b><a href="/halaman/status-order" target="_blank">Informasi Status Order</a></b>.</li>
						<li>Memahami & mengikuti <b><a href="/halaman/ketentuan-layanan" target="_blank">Ketentuan Layanan</a></b> & <b><a href="/halaman/pertanyaan-umum" target="_blank">FAQs</a></b>.</li>
					</ol>
				</div>

				<!--CARA-->
				<div class="table-responsive">
					<center><i aria-hidden="true" class="fa fa-check-circle"></i><b> Cara Melakukan Order</b></center>
					<ol class="list-p">
						<li>Pilih salah satu kategori & produk.</li>
						<li>Masukkan <u>no. pelanggan</u> / <u>no. tujuan</u> pada target (<b><a href="/halaman/target-pesanan" target="_blank">Lihat Contoh</a></b>).</li>
						<li>Pastikan nomor & format valid, contoh: 082377823390.</li>
						<li>Klik <span class="badge badge-primary"><b>Order</b></span> untuk memesan.</li>
					</ol>
				</div>

			</div>
		</div>
	</div>
	<!-- INFORMASI ORDER -->
</div>

<script type="text/javascript">
	$(document).ready(function() {
		$("#operator").change(function() {
			var operator = $("#operator").val();
			$.ajax({
				url: '<?php echo $config['web']['url'];?>ajax/layanan_mobilelegend.php',
				data: 'operator=' + operator,
				type: 'POST',
				dataType: 'html',
				success: function(msg) {
					$("#layanan").html(msg);
				}
			});
		});
		$("#layanan").change(function() {
			var layanan = $("#layanan").val();
			$.ajax({
				url: '<?php echo $config['web']['url'];?>ajax/harga_pulsa.php',
				data: 'layanan=' + layanan,
				type: 'POST',
				dataType: 'html',
				success: function(msg) {
					$("#harga").val(msg);
				}
			});
		});
		$("#layanan").change(function() {
			var layanan = $("#layanan").val();
			$.ajax({
				url: '<?php echo $config['web']['url'];?>ajax/catatan-pulsa.php',
				data: 'layanan=' + layanan,
				type: 'POST',
				dataType: 'html',
				success: function(msg) {
					$("#catatan").html(msg);
				}
			});
		});
	});
</script>

<script type="text/javascript">
	//Disable button
	(function() {
		$('input').keyup(function() {
			var empty = false;
			$('input').each(function() {
				if ($(this).val() == '') {
					empty = true;
				}
			});
			//Id button
			if (empty) {
				$('#checkout').attr('disabled', 'disabled');
			} else {
				$('#checkout').removeAttr('disabled');
			}
		});
	})()
</script>

<?php
require ("../lib/footer.php");
?>