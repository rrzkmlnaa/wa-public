<?php

function formatNomorTelepon(array $nomorList): array
{
  $formattedList = [];

  foreach ($nomorList as $nomor) {
    // Hapus spasi, strip, dan tanda plus
    $nomor = preg_replace('/[\s\-\+]/', '', $nomor);

    // Jika diawali dengan '0', ganti dengan '62'
    if (preg_match('/^0/', $nomor)) {
      $nomor = '62' . substr($nomor, 1);
    }
    // Jika diawali dengan '8', tambahkan '62' di depannya
    elseif (preg_match('/^8/', $nomor)) {
      $nomor = '62' . $nomor;
    }
    // Jika diawali dengan '620', ubah ke '62'
    elseif (preg_match('/^620/', $nomor)) {
      $nomor = '62' . substr($nomor, 3);
    }

    $formattedList[] = $nomor;
  }

  return $formattedList;
}
