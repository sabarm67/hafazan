<?php

namespace Database\Seeders;

use App\Models\Surah;
use Illuminate\Database\Seeder;

/**
 * Seeds surah-level metadata only (names, revelation type, ayah counts) —
 * this is standard reference data, not the Quran text itself. The
 * `name_translation_ms` values are a best-effort draft; verify them against
 * an authoritative Malay source (e.g. JAKIM) before relying on them in
 * production. Ayah text is populated separately via
 * `php artisan quran:import-tanzil` (see database/data/quran-uthmani/README.md).
 */
class SurahSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->surahs() as [$number, $nameArabic, $nameTransliteration, $nameTranslationMs, $revelationType, $totalAyat]) {
            Surah::updateOrCreate(
                ['number' => $number],
                [
                    'name_arabic' => $nameArabic,
                    'name_transliteration' => $nameTransliteration,
                    'name_translation_ms' => $nameTranslationMs,
                    'revelation_type' => $revelationType,
                    'total_ayat' => $totalAyat,
                ]
            );
        }
    }

    private function surahs(): array
    {
        return [
            [1, 'الفاتحة', 'Al-Fatihah', 'Pembukaan', 'meccan', 7],
            [2, 'البقرة', 'Al-Baqarah', 'Lembu Betina', 'medinan', 286],
            [3, 'آل عمران', "Aal-E-Imran", 'Keluarga Imran', 'medinan', 200],
            [4, 'النساء', 'An-Nisa', 'Wanita', 'medinan', 176],
            [5, 'المائدة', "Al-Ma'idah", 'Hidangan', 'medinan', 120],
            [6, 'الأنعام', "Al-An'am", 'Binatang Ternakan', 'meccan', 165],
            [7, 'الأعراف', "Al-A'raf", 'Tempat Tertinggi', 'meccan', 206],
            [8, 'الأنفال', 'Al-Anfal', 'Harta Rampasan Perang', 'medinan', 75],
            [9, 'التوبة', 'At-Tawbah', 'Taubat', 'medinan', 129],
            [10, 'يونس', 'Yunus', 'Yunus', 'meccan', 109],
            [11, 'هود', 'Hud', 'Hud', 'meccan', 123],
            [12, 'يوسف', 'Yusuf', 'Yusuf', 'meccan', 111],
            [13, 'الرعد', "Ar-Ra'd", 'Guruh', 'medinan', 43],
            [14, 'إبراهيم', 'Ibrahim', 'Ibrahim', 'meccan', 52],
            [15, 'الحجر', 'Al-Hijr', 'Al-Hijr', 'meccan', 99],
            [16, 'النحل', 'An-Nahl', 'Lebah', 'meccan', 128],
            [17, 'الإسراء', 'Al-Isra', 'Perjalanan Malam', 'meccan', 111],
            [18, 'الكهف', 'Al-Kahf', 'Gua', 'meccan', 110],
            [19, 'مريم', 'Maryam', 'Maryam', 'meccan', 98],
            [20, 'طه', 'Ta-Ha', 'Ta-Ha', 'meccan', 135],
            [21, 'الأنبياء', 'Al-Anbiya', 'Para Nabi', 'meccan', 112],
            [22, 'الحج', 'Al-Hajj', 'Haji', 'medinan', 78],
            [23, 'المؤمنون', "Al-Mu'minun", 'Orang-orang Yang Beriman', 'meccan', 118],
            [24, 'النور', 'An-Nur', 'Cahaya', 'medinan', 64],
            [25, 'الفرقان', 'Al-Furqan', 'Pembeza', 'meccan', 77],
            [26, 'الشعراء', "Ash-Shu'ara", 'Penyair-penyair', 'meccan', 227],
            [27, 'النمل', 'An-Naml', 'Semut', 'meccan', 93],
            [28, 'القصص', 'Al-Qasas', 'Kisah-kisah', 'meccan', 88],
            [29, 'العنكبوت', 'Al-Ankabut', 'Labah-labah', 'meccan', 69],
            [30, 'الروم', 'Ar-Rum', 'Rom', 'meccan', 60],
            [31, 'لقمان', 'Luqman', 'Luqman', 'meccan', 34],
            [32, 'السجدة', 'As-Sajdah', 'Sujud', 'meccan', 30],
            [33, 'الأحزاب', 'Al-Ahzab', 'Golongan Bersekutu', 'medinan', 73],
            [34, 'سبأ', 'Saba', "Saba'", 'meccan', 54],
            [35, 'فاطر', 'Fatir', 'Pencipta', 'meccan', 45],
            [36, 'يس', 'Ya-Sin', 'Yasin', 'meccan', 83],
            [37, 'الصافات', 'As-Saffat', 'Yang Berbaris', 'meccan', 182],
            [38, 'ص', 'Sad', 'Sad', 'meccan', 88],
            [39, 'الزمر', 'Az-Zumar', 'Rombongan-rombongan', 'meccan', 75],
            [40, 'غافر', 'Ghafir', 'Yang Mengampuni', 'meccan', 85],
            [41, 'فصلت', 'Fussilat', 'Yang Diterangkan', 'meccan', 54],
            [42, 'الشورى', 'Ash-Shura', 'Musyawarah', 'meccan', 53],
            [43, 'الزخرف', 'Az-Zukhruf', 'Perhiasan', 'meccan', 89],
            [44, 'الدخان', 'Ad-Dukhan', 'Kabut', 'meccan', 59],
            [45, 'الجاثية', 'Al-Jathiyah', 'Yang Bertekuk Lutut', 'meccan', 37],
            [46, 'الأحقاف', 'Al-Ahqaf', 'Bukit Pasir', 'meccan', 35],
            [47, 'محمد', 'Muhammad', 'Muhammad', 'medinan', 38],
            [48, 'الفتح', 'Al-Fath', 'Kemenangan', 'medinan', 29],
            [49, 'الحجرات', 'Al-Hujurat', 'Bilik-bilik', 'medinan', 18],
            [50, 'ق', 'Qaf', 'Qaf', 'meccan', 45],
            [51, 'الذاريات', 'Adh-Dhariyat', 'Angin Yang Menerbangkan', 'meccan', 60],
            [52, 'الطور', 'At-Tur', 'Bukit', 'meccan', 49],
            [53, 'النجم', 'An-Najm', 'Bintang', 'meccan', 62],
            [54, 'القمر', 'Al-Qamar', 'Bulan', 'meccan', 55],
            [55, 'الرحمن', 'Ar-Rahman', 'Yang Maha Pemurah', 'medinan', 78],
            [56, 'الواقعة', "Al-Waqi'ah", 'Hari Kiamat', 'meccan', 96],
            [57, 'الحديد', 'Al-Hadid', 'Besi', 'medinan', 29],
            [58, 'المجادلة', 'Al-Mujadilah', 'Wanita Yang Mengadu', 'medinan', 22],
            [59, 'الحشر', 'Al-Hashr', 'Pengusiran', 'medinan', 24],
            [60, 'الممتحنة', 'Al-Mumtahanah', 'Wanita Yang Diuji', 'medinan', 13],
            [61, 'الصف', 'As-Saff', 'Barisan', 'medinan', 14],
            [62, 'الجمعة', "Al-Jumu'ah", 'Hari Jumaat', 'medinan', 11],
            [63, 'المنافقون', 'Al-Munafiqun', 'Orang-orang Munafik', 'medinan', 11],
            [64, 'التغابن', 'At-Taghabun', 'Hari Ditampakkan Kesalahan', 'medinan', 18],
            [65, 'الطلاق', 'At-Talaq', 'Perceraian', 'medinan', 12],
            [66, 'التحريم', 'At-Tahrim', 'Pengharaman', 'medinan', 12],
            [67, 'الملك', 'Al-Mulk', 'Kerajaan', 'meccan', 30],
            [68, 'القلم', 'Al-Qalam', 'Pena', 'meccan', 52],
            [69, 'الحاقة', 'Al-Haqqah', 'Hari Kiamat', 'meccan', 52],
            [70, 'المعارج', "Al-Ma'arij", 'Tempat Naik', 'meccan', 44],
            [71, 'نوح', 'Nuh', 'Nuh', 'meccan', 28],
            [72, 'الجن', 'Al-Jinn', 'Jin', 'meccan', 28],
            [73, 'المزمل', 'Al-Muzzammil', 'Orang Yang Berselimut', 'meccan', 20],
            [74, 'المدثر', 'Al-Muddaththir', 'Orang Yang Berkemul', 'meccan', 56],
            [75, 'القيامة', 'Al-Qiyamah', 'Kebangkitan', 'meccan', 40],
            [76, 'الإنسان', 'Al-Insan', 'Manusia', 'medinan', 31],
            [77, 'المرسلات', 'Al-Mursalat', 'Yang Diutus', 'meccan', 50],
            [78, 'النبأ', 'An-Naba', 'Berita Besar', 'meccan', 40],
            [79, 'النازعات', "An-Nazi'at", 'Malaikat Yang Mencabut', 'meccan', 46],
            [80, 'عبس', 'Abasa', 'Dia Bermuka Masam', 'meccan', 42],
            [81, 'التكوير', 'At-Takwir', 'Menggulung', 'meccan', 29],
            [82, 'الانفطار', 'Al-Infitar', 'Terbelah', 'meccan', 19],
            [83, 'المطففين', 'Al-Mutaffifin', 'Orang Yang Curang', 'meccan', 36],
            [84, 'الانشقاق', 'Al-Inshiqaq', 'Terbelah', 'meccan', 25],
            [85, 'البروج', 'Al-Buruj', 'Gugusan Bintang', 'meccan', 22],
            [86, 'الطارق', 'At-Tariq', 'Bintang Yang Muncul Malam Hari', 'meccan', 17],
            [87, 'الأعلى', "Al-A'la", 'Yang Paling Tinggi', 'meccan', 19],
            [88, 'الغاشية', 'Al-Ghashiyah', 'Hari Pembalasan', 'meccan', 26],
            [89, 'الفجر', 'Al-Fajr', 'Fajar', 'meccan', 30],
            [90, 'البلد', 'Al-Balad', 'Negeri', 'meccan', 20],
            [91, 'الشمس', 'Ash-Shams', 'Matahari', 'meccan', 15],
            [92, 'الليل', 'Al-Layl', 'Malam', 'meccan', 21],
            [93, 'الضحى', 'Ad-Duha', 'Waktu Dhuha', 'meccan', 11],
            [94, 'الشرح', 'Ash-Sharh', 'Melapangkan', 'meccan', 8],
            [95, 'التين', 'At-Tin', 'Buah Tin', 'meccan', 8],
            [96, 'العلق', 'Al-Alaq', 'Segumpal Darah', 'meccan', 19],
            [97, 'القدر', 'Al-Qadr', 'Kemuliaan', 'meccan', 5],
            [98, 'البينة', 'Al-Bayyinah', 'Bukti Nyata', 'medinan', 8],
            [99, 'الزلزلة', 'Az-Zalzalah', 'Kegoncangan', 'medinan', 8],
            [100, 'العاديات', 'Al-Adiyat', 'Kuda Perang Yang Berlari Kencang', 'meccan', 11],
            [101, 'القارعة', "Al-Qari'ah", 'Hari Kiamat', 'meccan', 11],
            [102, 'التكاثر', 'At-Takathur', 'Bermegah-megah', 'meccan', 8],
            [103, 'العصر', 'Al-Asr', 'Masa', 'meccan', 3],
            [104, 'الهمزة', 'Al-Humazah', 'Pengumpat', 'meccan', 9],
            [105, 'الفيل', 'Al-Fil', 'Gajah', 'meccan', 5],
            [106, 'قريش', 'Quraysh', 'Quraisy', 'meccan', 4],
            [107, 'الماعون', "Al-Ma'un", 'Barang-barang Yang Berguna', 'meccan', 7],
            [108, 'الكوثر', 'Al-Kawthar', 'Nikmat Yang Banyak', 'meccan', 3],
            [109, 'الكافرون', 'Al-Kafirun', 'Orang-orang Kafir', 'meccan', 6],
            [110, 'النصر', 'An-Nasr', 'Pertolongan', 'medinan', 3],
            [111, 'المسد', 'Al-Masad', 'Sabut/Tali', 'meccan', 5],
            [112, 'الإخلاص', 'Al-Ikhlas', 'Keikhlasan', 'meccan', 4],
            [113, 'الفلق', 'Al-Falaq', 'Waktu Subuh', 'meccan', 5],
            [114, 'الناس', 'An-Nas', 'Manusia', 'meccan', 6],
        ];
    }
}
