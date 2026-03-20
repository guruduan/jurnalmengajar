define(['jquery'], function($) {
    return {
        init: function() {
            function loadSiswa(kelasid) {
                if (!kelasid) return;
                $.get("/local/jurnalmengajar/get_students.php", {kelas: kelasid}, function(html) {
                    $("#absen-area").html(html);
                    attachLogic(); // setelah render ulang
                });
            }

            function attachLogic() {
                $(".absen-checkbox").on("change", function() {
                    const checked = $(this).is(':checked');
                    const dropdown = $(this).closest('.absen-item').find('.absen-alasan');
                    dropdown.prop('disabled', !checked);
                    updateAbsenField();
                });

                $(".absen-alasan").on("change", function() {
                    updateAbsenField();
                });
            }

            function updateAbsenField() {
                let data = {};
                $(".absen-checkbox:checked").each(function() {
                    const nama = $(this).data("nama");
                    const alasan = $(this).closest('.absen-item').find('.absen-alasan').val();
                    if (alasan) data[nama] = alasan;
                });
                $("textarea[name=absen]").val(JSON.stringify(data));
            }

            // Trigger pertama kali
            const select = $("select[name=kelas]");
            if (select.val()) loadSiswa(select.val());

            select.on("change", function() {
                loadSiswa($(this).val());
            });
        }
    };
});
