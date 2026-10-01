<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Patient</th>
            <th>Age</th>
            <th>Gender</th>
            <th>Gmail</th>
            <th>Phone Number</th>
            <th>Services</th>
            <th>Total Price</th>
            <th>Date Visit</th>
            <th>Time</th>
            <th>Dentist</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Proof</th>
            <th>X-Ray</th>
            <th>Created At</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>

    <?php if (empty($approved)): ?>
      <tr><td colspan="17" style="text-align:center;">No approved appointment found.</td></tr>
    <?php endif; ?>

    <?php foreach ($approved as $index => $row): ?>
        <?php
            $service_stmt->execute([$row['id']]);
            $services = $service_stmt->fetchAll(PDO::FETCH_ASSOC);
            $service_names = implode(", ", array_column($services, 'service_name'));
            $total_price = array_sum(array_column($services, 'price'));
        ?>
        <tr>
            <td><?= $index + 1 ?></td>
            <td><?= htmlspecialchars($row['full_name']) ?></td>
            <td><?= $row['age'] ?></td>
            <td><?= $row['gender'] ?></td>
            <td><?= htmlspecialchars($row['gmail']) ?></td>
            <td><?= htmlspecialchars($row['phone_number']) ?></td>
            <td><?= $service_names ?></td>
            <td><?= number_format($total_price, 2) ?></td>
            <td><?= $row['date_visit'] ?></td>
            <td><?= date("g:i A", strtotime($row['time_visit'])) ?></td>
            <td><?= htmlspecialchars($row['dentist_name']) ?></td>
            <td><?= $row['status'] ?></td>
            <td><?= htmlspecialchars($row['payment_method'] ?? 'Cash') ?></td>
            <td>
                <?php if (!empty($row['proof_of_payment'])): ?>
                    <?php if (in_array($row['payment_method'], ['GCash', 'Online', 'Down Payment'])): ?>
                        <span style="font-size:0.85rem; color:#007bff;"><?= htmlspecialchars($row['proof_of_payment']) ?></span>
                    <?php else: ?>
                        <a href="../patient_side/uploads/payments/<?= htmlspecialchars(basename($row['proof_of_payment'])) ?>" target="_blank" style="color:#007bff; text-decoration:underline;">View</a>
                    <?php endif; ?>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>

            <!-- Auto-Detecting X-Ray Path Cell -->
            <td>
                <?php if (!empty($row['dental_toothxray'])): ?>
                    <?php 
                        $xray_file = basename($row['dental_toothxray']);
                        $doc_root = $_SERVER['DOCUMENT_ROOT'];
                        $xray_path = false;

                        $possible_paths = [
                            "/Mariategue-DentalClinic/uploads/xrays/" . $xray_file,
                            "/Mariategue-DentalClinic/uploads/" . $xray_file,
                        ];

                        foreach ($possible_paths as $p) {
                            if (file_exists($doc_root . $p)) {
                                $xray_path = htmlspecialchars($p);
                                break;
                            }
                        }
                    ?>

                    <?php if ($xray_path): ?>
                        <a href="<?= $xray_path ?>" target="_blank">
                            <img src="<?= $xray_path ?>" 
                                 alt="X-Ray" 
                                 style="width: 45px; height: 45px; object-fit: cover; border-radius: 4px; border: 1px solid #ccc; cursor: pointer;"
                                 title="Click to view full size">
                        </a>
                    <?php else: ?>
                        <span style="color:#dc3545; font-size:11px; font-weight:bold;">Missing File</span>
                    <?php endif; ?>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>

            <td><?= date("Y-m-d h:i A", strtotime($row['created_at'])) ?></td>
            <td>
                <!-- Print Prescription Button -->
                <button type="button" 
                        class="btn-approve" 
                        style="background-color: #17a2b8 !important; color: #fff; margin-bottom: 4px;" 
                        onclick="printPrescription(<?= htmlspecialchars(json_encode([
                            'id' => $row['id'],
                            'patient' => $row['full_name'],
                            'age' => $row['age'],
                            'gender' => $row['gender'],
                            'date' => $row['date_visit'],
                            'dentist' => $row['dentist_name'],
                            'services' => $service_names
                        ])) ?>)">
                    Print Prescription
                </button>

                <button class="btn-cancel" onclick='handleAction(<?= $row["id"] ?>, "CANCEL")'>Cancel</button>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- JavaScript Function para sa Pag-print ng Prescription -->
<script>
function printPrescription(data) {
    const printWindow = window.open('', '_blank', 'width=800,height=900');
    
    const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Prescription - ${data.patient}</title>
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    padding: 40px;
                    color: #333;
                }
                .header {
                    text-align: center;
                    border-bottom: 2px solid #007bff;
                    padding-bottom: 15px;
                    margin-bottom: 30px;
                }
                .header h2 {
                    margin: 0;
                    color: #007bff;
                    text-transform: uppercase;
                    font-weight: bold;
                    letter-spacing: 0.5px;
                }
                .header p {
                    margin: 5px 0 0 0;
                    font-size: 14px;
                    color: #666;
                }
                .info-section {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 30px;
                    font-size: 15px;
                }
                .info-section div {
                    line-height: 1.6;
                }
                .rx-symbol {
                    font-size: 48px;
                    font-weight: bold;
                    font-family: 'Georgia', serif;
                    color: #007bff;
                    margin-bottom: 10px;
                }
                .prescription-body {
                    min-height: 250px;
                    border: 1px dashed #ccc;
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 40px;
                }
                .footer {
                    margin-top: 50px;
                    display: flex;
                    justify-content: flex-end;
                }
                .signature-box {
                    text-align: center;
                    width: 250px;
                }
                .signature-line {
                    border-top: 1px solid #333;
                    margin-top: 60px;
                    margin-bottom: 5px;
                }
                @media print {
                    body { padding: 20px; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Mariategue Ortho-Dental Clinic</h2>
                <p>Orthodontics & General Dentistry Services</p>
            </div>

            <div class="info-section">
                <div>
                    <strong>Patient Name:</strong> ${data.patient}<br>
                    <strong>Age / Gender:</strong> ${data.age} / ${data.gender}<br>
                    <strong>Service:</strong> ${data.services}
                </div>
                <div style="text-align: right;">
                    <strong>Date:</strong> ${data.date}<br>
                    <strong>Prescription No:</strong> RX-${String(data.id).padStart(5, '0')}
                </div>
            </div>

            <div class="rx-symbol">Rx</div>

            <div class="prescription-body">
                <!-- Dito isusulat/lalabas ang mga gamot -->
                <p style="color: #888; font-style: italic;">[ Write prescription/medication details here ]</p>
            </div>

            <div class="footer">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <strong>Dr. ${data.dentist}</strong><br>
                    <span style="font-size: 12px; color: #555;">Licensed Dentist</span>
                </div>
            </div>

            <script>
                window.onload = function() {
                    window.print();
                };
            <\/script>
        </body>
        </html>
    `;

    printWindow.document.write(htmlContent);
    printWindow.document.close();
}
</script>