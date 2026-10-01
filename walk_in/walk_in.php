<?php
session_start();
include __DIR__ . '/../miscellaneous/database.php';

// 1. AJAX Endpoint para sa pagkuha ng dentista (Dapat nasa unahan bago mag-load ang sidebar o HTML)
if (isset($_GET['ajax_get_dentists']) && isset($_GET['date'])) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    try {
        $date = $_GET['date'];
        
        // Kunin ang pangalan ng araw sa maliit na titik (hal. monday, tuesday...)
        $dayColumn = strtolower(date('l', strtotime($date)));

        $sql = "
            SELECT d.id, CONCAT(d.first_name, ' ', d.last_name) AS name 
            FROM dentist_accounts d
            JOIN dentist_schedule s ON d.id = s.dentist_id
            WHERE d.is_deleted = 0 AND s.$dayColumn = 1
            ORDER BY d.first_name ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($dentists);
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// Isama na ang sidebar pagkatapos ng AJAX check
include __DIR__ . '/../miscellaneous/auth_check.php';
include __DIR__ . '/../miscellaneous/sidebar.php';

// Fetch active services
$services = $pdo->query("
    SELECT id, service_name, price 
    FROM services 
    WHERE status = 'enable'
    ORDER BY service_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-In Patient</title>
    <link rel="stylesheet" href="../miscellaneous/sidebar_design.css">
    <link rel="stylesheet" href="walk_in_design.css">
</head>

<body>

<!-- Success Notification Popup Banner sa Itaas -->
<div id="successBanner" class="success-banner">
    <span id="successMessage">
        <?php 
        if (isset($_SESSION['success_message'])) {
            echo htmlspecialchars($_SESSION['success_message']);
            unset($_SESSION['success_message']); 
        }
        ?>
    </span>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
<!-- Warning Dialog for errors (e.g. duplicate patient) -->
<div id="walkinErrorOverlay" style="
    display: flex;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.50);
    z-index: 999999;
    justify-content: center;
    align-items: center;
">
  <div style="
      background: #ffffff;
      border-radius: 16px;
      padding: 36px 32px 28px;
      max-width: 440px;
      width: 90%;
      text-align: center;
      box-shadow: 0 24px 64px rgba(0,0,0,0.22);
      animation: walkinPopIn 0.22s cubic-bezier(0.34,1.56,0.64,1);
  ">
    <div style="font-size: 2.6rem; margin-bottom: 12px; line-height: 1;">
      <i class="fa-solid fa-triangle-exclamation" style="color: #f59e0b;"></i>
    </div>
    <h3 style="color: #1e293b; margin: 0 0 10px; font-size: 1.15rem; font-weight: 700;">Warning</h3>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 0 0 16px;">
    <p style="color: #475569; font-size: 0.95rem; margin: 0 0 24px; line-height: 1.6;">
      <?= htmlspecialchars($_SESSION['error_message']) ?>
    </p>
    <button type="button"
      onclick="document.getElementById('walkinErrorOverlay').style.display='none'"
      style="padding: 10px 32px; background-color: #0ea5e9; color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 0.95rem; font-weight: 600;">
      OK
    </button>
  </div>
</div>
<style>
@keyframes walkinPopIn {
  from { transform: scale(0.80); opacity: 0; }
  to   { transform: scale(1);    opacity: 1; }
}
</style>
<?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<style>
/* Estilo para sa pop-up notification sa itaas */
.success-banner {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%) translateY(-100px);
    background-color: #4BB543;
    color: white;
    padding: 15px 30px;
    font-size: 16px;
    font-weight: bold;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 9999;
    opacity: 0;
    transition: transform 0.4s ease, opacity 0.4s ease;
}

.success-banner.show {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
}
</style>

<div class="main-content">

      <h2>Walk In</h2>
        <div class="table-wrapper">

        <form method="POST" action="walk_in_save.php">

            <!-- ========== PATIENT INFORMATION ========== -->

            <label>First Name</label>
            <input type="text" name="first_name" class="settings-input" required maxlength="50">

            <label>Middle Name (Optional)</label>
            <input type="text" name="middle_name" class="settings-input" maxlength="50">

            <label>Last Name</label>
            <input type="text" name="last_name" class="settings-input" required maxlength="50">

            <!-- IDINAGDAG NA ALLERGIES FIELD -->
            <label>Allergies (Optional)</label>
            <input type="text" name="allergies" class="settings-input" maxlength="255" placeholder="e.g. Penicillin, Latex (Type 'None' if none)">

            <label>Patient Type</label>
            <select name="patient_type" class="settings-input" required>
                <option value="New">New Patient</option>
                <option value="Returning">Returning Patient</option>
            </select>

            <label>Age</label>
            <input type="text" inputmode="numeric" pattern="[0-9]*" name="age" class="settings-input" required oninput="this.value = this.value.replace(/[^0-9]/g, ''); if(this.value.length > 3) this.value = this.value.slice(0, 3); if(this.value !== '' && parseInt(this.value) >= 150) this.value = '149';">

            <label>Gender</label>
            <select name="gender" class="settings-input" required>
                <option value="">-- Select Gender --</option>
                <option value="MALE">MALE</option>
                <option value="FEMALE">FEMALE</option>
            </select>

            <div class="input-with-checkbox">
                <label>Email</label>
                <label class="right-checkbox">
                    <input type="checkbox" id="emailNoneCheck" onclick="toggleEmailNone()">
                    <span>None</span>
                </label>
            </div>

            <input type="email" name="email" id="emailInput" class="settings-input" required maxlength="320">

            <label>Phone Number</label>
            <input type="text" 
                   name="phone_number" 
                   id="phoneInput" 
                   class="settings-input" 
                   maxlength="11" 
                   required>

            <label>Service:</label>
            <div id="selectedServicesContainer"></div>
            <input type="hidden" id="selectedServiceIds" name="service_ids">

            <select id="serviceSelect" class="settings-input" onchange="addService()">
                <option value="">-- Select Services --</option>
                <?php foreach ($services as $row): ?>
                    <option value="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['service_name']) ?>">
                        <?= htmlspecialchars($row['service_name']) ?> - ₱<?= number_format($row['price'], 2) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Date of Visit</label>
            <?php $today = date('Y-m-d'); ?>

            <input type="date" 
                   name="date_visit" 
                   class="settings-input" 
                   required 
                   id="walkinDate"
                   min="<?= $today ?>"
                   max="9999-12-31">

            <label>Select Dentist</label>
            <select name="dentist_id" class="settings-input" required id="walkinDentist" disabled>
                <option value="">Please select a date first</option>
            </select>

            <label>Time Visit:</label>
            <select name="time_slot" class="settings-input" id="timeSlotSelect" required disabled>
                <option value="">Please select a dentist first</option>
            </select>

            <label>Payment Method</label>
            <select name="payment_method" class="settings-input" required>
                <option value="Cash">Cash</option>
                <option value="GCash">GCash</option>
                <option value="Maya">Maya</option>
            </select>

            <button class="settings-btn" name="save_walkin" type="submit">Save Walk-In</button>

        </form>
    </div>
</div>

<script>
// Awtomatikong ipalabas ang banner sa itaas kung may success message galing sa PHP
document.addEventListener("DOMContentLoaded", function() {
    const banner = document.getElementById("successBanner");
    const message = document.getElementById("successMessage").textContent.trim();

    if (message !== "") {
        banner.classList.add("show");
        setTimeout(function() {
            banner.classList.remove("show");
        }, 3000);
    }
});

let selectedServices = [];
let selectedIds = [];

function addService() {
    const select = document.getElementById("serviceSelect");
    const selectedOption = select.options[select.selectedIndex];

    const id = selectedOption.value;
    const name = selectedOption.getAttribute("data-name");
    const priceText = selectedOption.text.split("₱")[1];
    const price = priceText ? `₱${priceText.trim()}` : "";

    if (!id) return;

    if (selectedIds.includes(id)) {
        showAlert("This service is already selected!");
        return;
    }

    selectedIds.push(id);
    selectedServices.push(name);
    selectedOption.disabled = true;

    document.getElementById("selectedServiceIds").value = selectedIds.join(",");

    const container = document.getElementById("selectedServicesContainer");
    const tag = document.createElement("div");
    tag.className = "service-tag";
    tag.dataset.id = id;
    tag.innerHTML = `
        <div class="service-content">
            <span class="service-name">${name}</span>
        </div>
        <span class="service-price">${price}</span>
        <span class="remove-x">Remove</span>
    `;

    tag.addEventListener("click", () => removeService(id));
    container.appendChild(tag);
    select.value = "";
}

function removeService(id) {
    const container = document.getElementById("selectedServicesContainer");
    const tag = container.querySelector(`[data-id='${id}']`);
    if (tag) tag.remove();

    const select = document.getElementById("serviceSelect");
    const option = select.querySelector(`option[value='${id}']`);
    if (option) option.disabled = false;

    const index = selectedIds.indexOf(id);
    if (index > -1) {
        selectedIds.splice(index, 1);
        selectedServices.splice(index, 1);
    }

    document.getElementById("selectedServiceIds").value = selectedIds.join(",");
}

document.addEventListener('DOMContentLoaded', function () {
    const dateInput   = document.getElementById('walkinDate');
    const timeSelect  = document.getElementById('timeSlotSelect');
    const dentistSelect = document.getElementById('walkinDentist');

    function updateTimeSlots() {
        const selectedDate = dateInput.value;
        const selectedDentist = dentistSelect.value;
        
        if (!selectedDate || !selectedDentist) {
            timeSelect.innerHTML = '<option value="">Please select a dentist first</option>';
            timeSelect.disabled = true;
            return;
        }

        timeSelect.disabled = false;
        timeSelect.innerHTML = '<option value="">Loading time slots...</option>';

        fetch(`walkin_get_slots.php?date=${selectedDate}&dentist_id=${selectedDentist}`)
            .then(async res => {
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Slot PHP Error:", text);
                    throw new Error("Invalid JSON");
                }
            })
            .then(data => {
                timeSelect.innerHTML = '<option value="">-- Select Time Slot --</option>';
                if (!Array.isArray(data) || data.length === 0) {
                    timeSelect.innerHTML = '<option value="">No time slots available</option>';
                    return;
                }
                data.forEach(slot => {
                    const option = document.createElement('option');
                    option.value = slot.time;
                    // Convert 24h to 12h AM/PM for display
                    const [h, m] = slot.time.split(':').map(Number);
                    const period = h < 12 ? 'AM' : 'PM';
                    const hour12 = h % 12 === 0 ? 12 : h % 12;
                    const displayTime = hour12 + ':' + String(m).padStart(2, '0') + ' ' + period;
                    option.textContent = displayTime + (slot.full ? ' (Full)' : '');
                    option.disabled = slot.full;
                    timeSelect.appendChild(option);
                });
            })
            .catch(err => {
                console.error("Error loading slots:", err);
                timeSelect.innerHTML = '<option value="">Error loading time slots</option>';
            });
    }

    dateInput.addEventListener('change', function () {
        const selectedDate = this.value;
        
        timeSelect.innerHTML = '<option value="">Please select a dentist first</option>';
        timeSelect.disabled = true;
        dentistSelect.innerHTML = '<option value="">Loading dentists...</option>';
        dentistSelect.disabled = true;

        if (!selectedDate) return;

        const day = new Date(selectedDate).getUTCDay();
        if (day === 0) {
            showAlert("Appointments are not allowed on Sundays.");
            this.value = '';
            dentistSelect.innerHTML = '<option value="">Please select a date first</option>';
            return;
        }

        fetch(`walk_in.php?ajax_get_dentists=1&date=${selectedDate}`)
            .then(async res => {
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Dentist PHP Error:", text);
                    throw new Error("Invalid JSON");
                }
            })
            .then(data => {
                dentistSelect.disabled = false;
                dentistSelect.innerHTML = '<option value="">-- Select a Dentist --</option>';
                if (!Array.isArray(data) || data.length === 0) {
                    dentistSelect.innerHTML = '<option value="">No dentist available on this date</option>';
                    return;
                }
                data.forEach(d => {
                    const option = document.createElement('option');
                    option.value = d.id;
                    option.textContent = d.name;
                    dentistSelect.appendChild(option);
                });
            })
            .catch(err => {
                console.error("Error loading dentists:", err);
                dentistSelect.innerHTML = '<option value="">Error loading dentists</option>';
            });
    });

    dentistSelect.addEventListener('change', updateTimeSlots);
});

function toggleEmailNone() {
    const checkbox = document.getElementById("emailNoneCheck");
    const input = document.getElementById("emailInput");

    if (checkbox.checked) {
        input.value = "None";
        input.readOnly = true;
        input.style.background = "#f1f1f1";
    } else {
        input.value = "";
        input.readOnly = false;
        input.style.background = "white";
    }
}

document.querySelector("form").addEventListener("submit", function(e) {
    const serviceIds = document.getElementById("selectedServiceIds").value;
    const dateVisit = document.getElementById("walkinDate").value;
    const timeSlot = document.getElementById("timeSlotSelect").value;
    const dentist = document.getElementById("walkinDentist").value;
    const phone = document.querySelector("input[name='phone_number']").value;
    const allergiesInput = document.querySelector("input[name='allergies']");

    if (allergiesInput && allergiesInput.value.trim() !== "") {
        allergiesInput.value = allergiesInput.value.toUpperCase();
    }

    if (serviceIds.trim() === "") {
        showAlert("Please select at least one service.");
        e.preventDefault();
        return;
    }

    if (!dateVisit || !timeSlot || !dentist) {
        showAlert("Please complete the date, dentist, and time slot selection.");
        e.preventDefault();
        return;
    }

    if (!/^(09)\d{9}$/.test(phone)) {
        showAlert("Phone number must start with 09 and be 11 digits.");
        e.preventDefault();
        return;
    }
});

document.addEventListener("DOMContentLoaded", function () {
    const phoneInput = document.getElementById("phoneInput");
    if(phoneInput && !phoneInput.value) phoneInput.value = "09";

    phoneInput.addEventListener("keydown", function (e) {
        if ((phoneInput.selectionStart < 2) && (e.key === "Backspace" || e.key === "Delete")) {
            e.preventDefault();
        }
    });

    phoneInput.addEventListener("input", function () {
        phoneInput.value = phoneInput.value.replace(/\D/g, "");
        if (!phoneInput.value.startsWith("09")) {
            phoneInput.value = "09";
        }
        if (phoneInput.value.length > 11) {
            phoneInput.value = phoneInput.value.slice(0, 11);
        }
    });
});

document.addEventListener("DOMContentLoaded", function () {
    const firstName = document.querySelector("input[name='first_name']");
    const middleName = document.querySelector("input[name='middle_name']");
    const lastName  = document.querySelector("input[name='last_name']");

    [firstName, middleName, lastName].forEach(input => {
        if(input) {
            input.addEventListener("input", function () {
                this.value = this.value.replace(/[^a-zA-Z\s]/g, '').toUpperCase();
            });
        }
    });
});

// =====================================================================
// FORM PERSISTENCE — saves all field values to localStorage so navigating
// away and coming back restores everything the user typed.
// Cleared only when the walk-in is saved successfully.
// =====================================================================
(function() {
    const STORAGE_KEY = 'walkin_form_draft';

    // Field selectors we want to persist
    const TEXT_FIELDS = [
        'input[name="first_name"]',
        'input[name="middle_name"]',
        'input[name="last_name"]',
        'input[name="allergies"]',
        'input[name="age"]',
        'input[name="email"]',
        'input[name="phone_number"]',
        'input[name="date_visit"]',
    ];
    const SELECT_FIELDS = [
        'select[name="patient_type"]',
        'select[name="gender"]',
        'select[name="payment_method"]',
    ];
    // date_visit, dentist, time_slot need special handling
    const DATE_FIELD    = 'walkinDate';
    const DENTIST_FIELD = 'walkinDentist';
    const TIME_FIELD    = 'timeSlotSelect';

    function saveDraft() {
        const draft = {};
        TEXT_FIELDS.forEach(sel => {
            const el = document.querySelector(sel);
            if (el) draft[el.name] = el.value;
        });
        SELECT_FIELDS.forEach(sel => {
            const el = document.querySelector(sel);
            if (el) draft[el.name] = el.value;
        });
        // email checkbox
        const emailCheck = document.getElementById('emailNoneCheck');
        if (emailCheck) draft['emailNoneCheck'] = emailCheck.checked;

        // Services (store id+name+price text)
        draft['selectedIds']      = [...selectedIds];
        draft['selectedServices'] = [...selectedServices];
        // Store the rendered tag HTML so we can rebuild them
        const container = document.getElementById('selectedServicesContainer');
        if (container) draft['serviceTagsHtml'] = container.innerHTML;

        // Date / dentist / time — only save value strings
        draft['date_visit']  = document.getElementById(DATE_FIELD)?.value || '';
        draft['dentist_id']  = document.getElementById(DENTIST_FIELD)?.value || '';
        draft['dentist_text']= document.getElementById(DENTIST_FIELD)?.options[document.getElementById(DENTIST_FIELD)?.selectedIndex]?.text || '';
        draft['time_slot']   = document.getElementById(TIME_FIELD)?.value || '';
        draft['time_text']   = document.getElementById(TIME_FIELD)?.options[document.getElementById(TIME_FIELD)?.selectedIndex]?.text || '';

        localStorage.setItem(STORAGE_KEY, JSON.stringify(draft));
    }

    function loadDraft() {
        let draft;
        try { draft = JSON.parse(localStorage.getItem(STORAGE_KEY)); } catch(e) {}
        if (!draft) return;

        // Restore text inputs
        TEXT_FIELDS.forEach(sel => {
            const el = document.querySelector(sel);
            if (el && draft[el.name] !== undefined) el.value = draft[el.name];
        });
        // Restore selects
        SELECT_FIELDS.forEach(sel => {
            const el = document.querySelector(sel);
            if (el && draft[el.name] !== undefined) el.value = draft[el.name];
        });
        // Email checkbox
        if (draft['emailNoneCheck']) {
            const emailCheck = document.getElementById('emailNoneCheck');
            const emailInput = document.getElementById('emailInput');
            if (emailCheck && emailInput) {
                emailCheck.checked = true;
                emailInput.readOnly = true;
                emailInput.style.background = '#f1f1f1';
            }
        }

        // Restore services
        if (Array.isArray(draft['selectedIds']) && draft['selectedIds'].length > 0) {
            // Rebuild the JS arrays
            selectedIds      = draft['selectedIds'];
            selectedServices = draft['selectedServices'] || [];
            document.getElementById('selectedServiceIds').value = selectedIds.join(',');

            // Rebuild the service tags
            const container = document.getElementById('selectedServicesContainer');
            if (container && draft['serviceTagsHtml']) {
                container.innerHTML = draft['serviceTagsHtml'];
                // Re-attach remove listeners and disable options in the select
                container.querySelectorAll('.service-tag').forEach(tag => {
                    const id = tag.dataset.id;
                    tag.addEventListener('click', () => removeService(id));
                    const opt = document.querySelector(`#serviceSelect option[value="${id}"]`);
                    if (opt) opt.disabled = true;
                });
            }
        }

        // Restore date/dentist/time — inject saved values as options so they show up
        const dateInput = document.getElementById(DATE_FIELD);
        if (dateInput && draft['date_visit']) {
            dateInput.value = draft['date_visit'];
        }

        if (draft['dentist_id'] && draft['dentist_text']) {
            const dentistSel = document.getElementById(DENTIST_FIELD);
            if (dentistSel) {
                dentistSel.innerHTML = '';
                // Add the saved dentist as an option so it displays
                const opt = document.createElement('option');
                opt.value = draft['dentist_id'];
                opt.textContent = draft['dentist_text'];
                opt.selected = true;
                dentistSel.appendChild(opt);
                dentistSel.disabled = false;
            }
        }

        if (draft['time_slot'] && draft['time_text']) {
            const timeSel = document.getElementById(TIME_FIELD);
            if (timeSel) {
                timeSel.innerHTML = '';
                const opt = document.createElement('option');
                opt.value = draft['time_slot'];
                // Always display in AM/PM format
                const rawTime = draft['time_slot'];
                const [h, m] = rawTime.split(':').map(Number);
                const period = h < 12 ? 'AM' : 'PM';
                const hour12 = h % 12 === 0 ? 12 : h % 12;
                const displayTime = hour12 + ':' + String(m).padStart(2, '0') + ' ' + period;
                opt.textContent = displayTime;
                opt.selected = true;
                timeSel.appendChild(opt);
                timeSel.disabled = false;
            }
        }
    }

    function clearDraft() {
        localStorage.removeItem(STORAGE_KEY);
    }

    // Attach save-on-input listeners to all persistent fields
    function attachSaveListeners() {
        TEXT_FIELDS.concat(SELECT_FIELDS).forEach(sel => {
            const el = document.querySelector(sel);
            if (el) {
                el.addEventListener('input',  saveDraft);
                el.addEventListener('change', saveDraft);
            }
        });
        const emailCheck = document.getElementById('emailNoneCheck');
        if (emailCheck) emailCheck.addEventListener('change', saveDraft);

        // Date / dentist / time
        document.getElementById(DATE_FIELD)?.addEventListener('change', saveDraft);
        document.getElementById(DENTIST_FIELD)?.addEventListener('change', saveDraft);
        document.getElementById(TIME_FIELD)?.addEventListener('change', saveDraft);

        // Also save whenever a service tag is added/removed
        // (addService / removeService call saveDraft via MutationObserver below)
        const container = document.getElementById('selectedServicesContainer');
        if (container) {
            new MutationObserver(saveDraft).observe(container, { childList: true, subtree: true });
        }
    }

    // Clear draft on successful form submission (success banner is shown)
    function checkSuccessAndClear() {
        const message = document.getElementById('successMessage')?.textContent?.trim();
        if (message && message !== '') {
            clearDraft();
        }
    }

    // Run on page load
    loadDraft();
    attachSaveListeners();
    checkSuccessAndClear();
})();

</script>

</body>
</html>

