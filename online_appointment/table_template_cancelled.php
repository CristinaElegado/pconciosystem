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

    <?php if (empty($cancelled)): ?>
      <tr><td colspan="17" style="text-align:center;">No cancelled appointment found.</td></tr>
    <?php endif; ?>

    <?php foreach ($cancelled as $index => $row): ?>
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
            <td><?= $row['dentist_name'] ?></td>
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
                        // Use __DIR__ so it works on both local and Railway/production
                        $xray_abs  = __DIR__ . '/../uploads/xrays/' . $xray_file;
                        $xray_url  = '../uploads/xrays/' . rawurlencode($xray_file);
                    ?>
                    <?php if (file_exists($xray_abs)): ?>
                        <a href="<?= $xray_url ?>" target="_blank">
                            <img src="<?= $xray_url ?>"
                                 alt="X-Ray"
                                 style="width:45px;height:45px;object-fit:cover;border-radius:4px;border:1px solid #ccc;cursor:pointer;"
                                 title="Click to view full size">
                        </a>
                    <?php else: ?>
                        <span style="color:#dc3545;font-size:11px;font-weight:bold;">Missing File</span>
                    <?php endif; ?>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>

            <td><?= date("Y-m-d h:i A", strtotime($row['created_at'])) ?></td>
            <td>
                <?php if ($row['status'] === 'CANCELLED'): ?>
                    <button class="btn-approve" onclick='handleAction(<?= $row["id"] ?>, "RESTORE")'>Restore</button>
                    <?php if ($row['payment_method'] !== 'Cash'): ?>
                        <button class="btn-cancel" onclick='handleAction(<?= $row["id"] ?>, "REFUND")'>Refund</button>
                    <?php endif; ?>
                <?php elseif ($row['status'] === 'REFUNDED'): ?>
                    <span style="color: #f59e0b; font-weight: bold;">Refunded</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
