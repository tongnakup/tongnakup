<?php
// --- 0. โหลดไลบรารีและตั้งค่าพื้นฐาน ---
require 'vendor/autoload.php';

// ตั้งค่าโซนเวลาให้เป็นของประเทศไทย เพื่อให้เวลาที่พิมพ์ถูกต้อง
date_default_timezone_set('Asia/Bangkok');

use Picqer\Barcode\BarcodeGeneratorPNG;

// --- 1. การตั้งค่าฐานข้อมูล ---
$servername = "192.168.111.52";
$username = "root";
$password = "Anji@12345";
$dbname = "vdc_db";
$port = 3308;

$label_to_print = null;
$error_message = null;

// --- 2. ตรวจสอบว่ามีการสแกนข้อมูลเข้ามาหรือไม่ ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['scanned_vin'])) {
    $scanned_vin = trim($_POST['scanned_vin']);
    $conn = new mysqli($servername, $username, $password, $dbname, $port);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");

    // --- SQL QUERY ที่มีการ JOIN เพื่อดึงชื่อ Model ---
    $sql = "SELECT
                g.vin_number,
                c.topic AS model_name
            FROM
                gcms_gaoff AS g
            INNER JOIN
                gcms_vehicle_code AS v ON g.vc_code = v.vehicle_code
            INNER JOIN
                gcms_category AS c ON v.model = c.category_id
            WHERE
                TRIM(g.vin_number) = ?
                AND c.type = 'vehicle_model'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $scanned_vin);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $label_to_print = $result->fetch_assoc();
    } else {
        $error_message = "ไม่พบข้อมูล Vin No: " . htmlspecialchars($scanned_vin);
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan to Print</title>
    <style>
        @page {
            size: auto;
            margin: 0mm;
        }

        body {
            font-family: 'Tahoma', sans-serif;
            background-color: #f4f4f4;
            text-align: center;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        input[type="text"] {
            font-size: 1.1em;
            padding: 10px;
            width: 80%;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        button {
            font-size: 1.1em;
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background-color: #007bff;
            color: white;
            border-radius: 4px;
        }

        .error {
            color: red;
            font-weight: bold;
            margin-top: 15px;
        }

        .label {
            margin: 0 auto;
            background-color: white;
            width: 80mm;
            height: 50mm;
            border: 1px solid #ccc;
            padding: 4mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .barcode-section {
            text-align: center;
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .barcode-section img {
            width: 100%;
            height: 15mm;
            object-fit: fill;
        }

        .details-section {
            flex-shrink: 0;
        }

        .detail-row {
            display: flex;
            align-items: baseline;
            margin-bottom: 5px;
            font-size: 10pt;
        }

        .detail-row .title {
            flex-shrink: 0;
            margin-right: 8px;
            width: 90px;
        }

        .detail-row .value {
            font-weight: bold;
            overflow-wrap: break-word;
            word-break: break-all;
            white-space: normal;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
                background-color: white;
            }

            .no-print {
                display: none;
            }

            .label-wrapper {
                margin: 0;
                padding: 0;
                box-shadow: none;
                border: none;
            }

            .label {
                border: none;
                box-shadow: none;
                margin: 0;
            }
        }

        @media (max-width: 600px) {
            .label {
                width: 95%;
                height: auto;
                aspect-ratio: 80 / 50;
                padding: 3mm;
            }

            .detail-row {
                font-size: 8pt;
            }

            .detail-row .title {
                margin-right: 5px;
                width: 70px;
            }
        }
    </style>
</head>

<body>

    <?php if ($label_to_print): ?>

        <div class="label-wrapper">
            <div class="no-print" style="margin-bottom: 20px;">
                <a href="index.php"><button>สแกนรายการถัดไป</button></a>
            </div>
            <?php
            $generator = new BarcodeGeneratorPNG();
            $vin_no = htmlspecialchars($label_to_print["vin_number"]);

            // ดึง 'model_name' จากผลลัพธ์ SQL
            $model = htmlspecialchars($label_to_print['model_name'] ?? '');

            // สร้างวันที่และเวลาปัจจุบัน ณ ตอนที่พิมพ์
            $date = date('d-m-Y H:i:s');

            $barcodeImage = $generator->getBarcode($vin_no, $generator::TYPE_CODE_128, 2, 50);
            $barcodeUri = 'data:image/png;base64,' . base64_encode($barcodeImage);

            echo "
            <div class='label'>
                <div class='barcode-section'><img src='{$barcodeUri}' alt='Barcode for {$vin_no}'></div>
                <div class='details-section'>
                    <div class='detail-row'><span class='title'>Vin No</span><span class='value'>{$vin_no}</span></div>
                    <div class='detail-row'><span class='title'>Model</span><span class='value'>{$model}</span></div>
                    <div class='detail-row'><span class='title'>Received_Date</span><span class='value'>{$date}</span></div>
                    <div class='detail-row'><span class='title'>Location Code</span><span class='value'>TRANSIT</span></div>
                </div>
            </div>";
            ?>
        </div>
        <script type="text/javascript">
            window.print();
        </script>

    <?php else: ?>

        <div class="container">
            <h1>Scan to Print</h1>
            <form action="index.php" method="POST" id="scan_form">
                <input type="text" name="scanned_vin" id="scanned_vin_input" autofocus autocomplete="off" placeholder="ยิง Barcode ที่นี่...">
                <button type="submit" style="display: none;">Submit</button>
            </form>
            <?php if ($error_message): ?>
                <p class="error"><?php echo $error_message; ?></p>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <script type="text/javascript">
        // ตั้งค่าตัวแปรสำหรับหน่วงเวลา (debounce timer)
        let typingTimer;
        const doneTypingInterval = 200; // เวลาหน่วง (มิลลิวินาที) หลังจากพิมพ์เสร็จ
        const myInput = document.getElementById('scanned_vin_input');
        const myForm = document.getElementById('scan_form');

        // ตรวจสอบว่าเราอยู่ในหน้าสแกนหรือไม่ (ป้องกันการทำงานผิดหน้า)
        if (myInput && myForm) {
            // เมื่อมีการพิมพ์ในช่อง input
            myInput.addEventListener('keyup', () => {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(doneTyping, doneTypingInterval);
            });

            // ฟังก์ชันที่จะทำงานเมื่อพิมพ์เสร็จ
            function doneTyping() {
                // ตรวจสอบว่ามีข้อมูลในช่อง input หรือไม่
                if (myInput.value.length > 0) {
                    myForm.submit(); // สั่งให้ฟอร์มส่งข้อมูล
                }
            }
        }
    </script>
</body>

</html>