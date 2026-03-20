<?php
namespace local_jurnalmengajar\external;

defined('MOODLE_INTERNAL') || die();

class mobile {
    public static function mobile_view() {
        global $CFG;

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => '
                        <ion-item>
                            <ion-label>
                                <h2>Jurnal Mengajar</h2>
                                <p>Tekan untuk membuka halaman jurnal di browser</p>
                            </ion-label>
                        </ion-item>
                        <ion-item>
                            <a href="'.$CFG->wwwroot.'/local/jurnalmengajar/index.php" target="_system">📘 Buka Jurnal</a>
                        </ion-item>
                    '
                ]
            ]
        ];
    }
}
