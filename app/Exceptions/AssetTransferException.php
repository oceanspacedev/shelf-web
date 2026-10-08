<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Aturan siklus hidup BA menolak transfer (pihak bukan staf GA, aset tidak di
 * stok, dan sebagainya). Pesannya ditulis untuk pengguna dan aman ditampilkan
 * apa adanya; error lain (database, HTTP) tidak memakai kelas ini.
 */
class AssetTransferException extends RuntimeException {}
