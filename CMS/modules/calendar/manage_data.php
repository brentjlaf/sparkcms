<?php
/************************************************************
 * manage_data.php
 * 
 * A single admin page to manage categories & events from
 * one JSON file: calendar_data.json
 ************************************************************/

require_once __DIR__ . '/../../includes/auth.php';
require_login();

define('CALENDAR_DATA_FILE', __DIR__ . '/../../data/calendar_data.json');

/** Read JSON => { 'categories': [...], 'events': [...] } */
function readData() {
    if (!file_exists(CALENDAR_DATA_FILE)) {
        return ['categories'=>[], 'events'=>[]];
    }
    $json = file_get_contents(CALENDAR_DATA_FILE);
    $data = json_decode($json, true);
    return $data ?? ['categories'=>[], 'events'=>[]];
}

/** Write data */
function writeData($data) {
    file_put_contents(CALENDAR_DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

/** Helper: new cat ID */
function generateCategoryId($cats) {
    $max = 0;
    foreach ($cats as $c) {
        if (($c['id'] ?? 0) > $max) $max = (int) $c['id'];
    }
    return $max + 1;
}

/** Helper: new event ID */
function generateEventId($events) {
    $max = 0;
    foreach ($events as $e) {
        if (($e['id'] ?? 0) > $max) $max = (int) $e['id'];
    }
    return $max + 1;
}

/** Convert "2025-01-10 15:00:00" => "2025-01-10T15:00" for datetime-local */
function toDateTimeLocal($dt) {
    if (!$dt) return '';
    $ts = strtotime($dt);
    if (!$ts) return '';
    return date('Y-m-d\TH:i', $ts);
}

// Load data
$data = readData();
$categories = $data['categories'];
$events = $data['events'];

$message = '';
$action  = $_GET['action'] ?? '';

// ================ CATEGORIES =================
if ($action === 'add_category' && $_SERVER['REQUEST_METHOD']==='POST') {
    $name  = trim($_POST['cat_name'] ?? '');
    $color = trim($_POST['cat_color'] ?? '#ffffff');
    if ($name !== '') {
        $newId = generateCategoryId($categories);
        $categories[] = [
            'id'    => $newId,
            'name'  => $name,
            'color' => $color
        ];
        $data['categories'] = $categories;
        writeData($data);
        $message = "Category '$name' added.";
    }
}
elseif ($action==='delete_category' && isset($_GET['cat_id'])) {
    $cid = intval($_GET['cat_id']);
    foreach ($categories as $idx=>$cat) {
        if (($cat['id'] ?? 0)===$cid) {
            $message = "Category '" . ($cat['name'] ?? 'Category') . "' deleted.";
            array_splice($categories,$idx,1);
            $data['categories']=$categories;
            writeData($data);
            break;
        }
    }
}

// ================ EVENTS =================
if ($action==='delete_event' && isset($_GET['evt_id'])) {
    $eid = intval($_GET['evt_id']);
    foreach ($events as $idx=>$ev) {
        if (($ev['id'] ?? 0)===$eid) {
            $message = "Event #$eid ('" . ($ev['title'] ?? 'Event') . "') deleted.";
            array_splice($events,$idx,1);
            $data['events']=$events;
            writeData($data);
            break;
        }
    }
}
elseif ($action==='save_event' && $_SERVER['REQUEST_METHOD']==='POST') {
    $evtId = trim($_POST['evt_id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $start = trim($_POST['start_date'] ?? '');
    if ($title && $start) {
        $desc  = trim($_POST['description'] ?? '');
        $end   = trim($_POST['end_date'] ?? '');
        $cat   = trim($_POST['category'] ?? '');
        $rInt  = trim($_POST['recurring_interval'] ?? 'none');
        $rEnd  = trim($_POST['recurring_end_date'] ?? '');

        if ($evtId==='') {
            // add new
            $newId = generateEventId($events);
            $newEvt = [
                'id'                 => $newId,
                'title'              => $title,
                'description'        => $desc,
                'start_date'         => $start,
                'end_date'           => $end,
                'category'           => $cat,
                'recurring_interval' => $rInt,
                'recurring_end_date' => $rEnd
            ];
            $events[]=$newEvt;
            $data['events']=$events;
            writeData($data);
            $message="New event (#$newId) added.";
        } else {
            // update existing
            $found=false;
            foreach ($events as &$evt) {
                if (($evt['id'] ?? null)==$evtId) {
                    $evt['title']              = $title;
                    $evt['description']        = $desc;
                    $evt['start_date']         = $start;
                    $evt['end_date']           = $end;
                    $evt['category']           = $cat;
                    $evt['recurring_interval'] = $rInt;
                    $evt['recurring_end_date'] = $rEnd;
                    $found=true;
                    break;
                }
            }
            unset($evt);
            if($found) {
                $data['events']=$events;
                writeData($data);
                $message="Event #$evtId updated.";
            } else {
                $message="Event #$evtId not found.";
            }
        }
    } else {
        $message="Title & Start Date are required.";
    }
}

// Reload after changes
$data=readData();
$categories=$data['categories'];
$events=$data['events'];

// Check if editing an event
$editingEvent = [
 'id'=>'',
 'title'=>'',
 'description'=>'',
 'start_date'=>'',
 'end_date'=>'',
 'category'=>'',
 'recurring_interval'=>'none',
 'recurring_end_date'=>''
];
$editingEventId = 0;
$openNewEventModal = isset($_GET['new_event']);
if(isset($_GET['edit_event'])) {
    $editingEventId = intval($_GET['edit_event']);
    foreach ($events as $ev) {
        if(($ev['id'] ?? 0)===$editingEventId) {
            $editingEvent=$ev;
            break;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Calendar Data</title>
  <link 
    rel="stylesheet" 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
/>
</head>
<body>
<div class="container py-4">
  <h1>Manage Calendar Data</h1>
  <?php if($message):?>
    <div class="alert alert-info"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif;?>

  <p>
    <a href="../../admin.php#calendar" class="btn btn-secondary">← View Calendar</a>
    <!-- Button to open the Add/Edit Event modal for "New Event" -->
    <button 
      type="button"
      class="btn btn-primary"
      data-bs-toggle="modal" 
      data-bs-target="#manageEventModal"
      onclick="document.getElementById('evt_id_field').value='';"
    >
      + Add New Event
    </button>
    <!-- New Manage Categories Button -->
    <button 
      type="button"
      class="btn btn-outline-secondary"
      data-bs-toggle="modal" 
      data-bs-target="#manageCategories"
    >
      Manage Categories
    </button>
  </p>

  <!-- List of events -->
  <div class="card mb-4">
    <div class="card-header"><strong>All Events</strong></div>
    <div class="card-body p-0">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Start</th>
            <th>End</th>
            <th>Category</th>
            <th>Recurrence</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if(!empty($events)):?>
          <?php foreach($events as $evt):?>
          <tr>
            <td><?php echo htmlspecialchars($evt['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($evt['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($evt['start_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($evt['end_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($evt['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($evt['recurring_interval'] ?? 'none', ENT_QUOTES, 'UTF-8'); ?></td>
            <td style="width:180px;">
              <!-- "Edit" button opens the same modal, but we send user to ?edit_event=ID
                   so the server can populate the fields, and we'll auto-trigger the modal. -->
              <a href="?edit_event=<?php echo urlencode((string)($evt['id'] ?? '')); ?>" 
                 class="btn btn-sm btn-primary">
                 Edit
              </a>
              <a href="?action=delete_event&amp;evt_id=<?php echo urlencode((string)($evt['id'] ?? '')); ?>"
                 class="btn btn-sm btn-danger"
                 onclick="return confirm('Delete event #<?php echo htmlspecialchars($evt['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>?');">
                 Delete
              </a>
            </td>
          </tr>
          <?php endforeach;?>
        <?php else:?>
          <tr><td colspan="7">No events found.</td></tr>
        <?php endif;?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ================== ADD/EDIT EVENT MODAL ================== -->
<div class="modal fade" id="manageEventModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <?php if(!empty($editingEvent['id'])):?>
          <h5 class="modal-title">Edit Event #<?php echo htmlspecialchars($editingEvent['id'], ENT_QUOTES, 'UTF-8'); ?></h5>
        <?php else:?>
          <h5 class="modal-title">Add New Event</h5>
        <?php endif;?>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form class="row g-3" method="POST" action="?action=save_event">
          <!-- Hidden ID field -->
          <input 
            type="hidden" 
            name="evt_id" 
            id="evt_id_field"
            value="<?php echo htmlspecialchars($editingEvent['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
          />

          <div class="col-md-6">
            <label class="form-label">Title*</label>
            <input 
              type="text" 
              name="title" 
              class="form-control"
              value="<?php echo htmlspecialchars($editingEvent['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
              required
            />
          </div>

          <div class="col-md-6">
            <label class="form-label">Category</label>
            <select name="category" class="form-select">
              <option value="">(None)</option>
              <?php foreach($categories as $cat):?>
                <option 
                  value="<?php echo htmlspecialchars($cat['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                  <?php if(($editingEvent['category'] ?? '')==($cat['name'] ?? '')) echo 'selected';?>
                >
                  <?php echo htmlspecialchars($cat['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach;?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Start Date/Time*</label>
            <input 
              type="datetime-local" 
              name="start_date" 
              class="form-control"
              value="<?php echo htmlspecialchars(toDateTimeLocal($editingEvent['start_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
              required
            />
          </div>
          <div class="col-md-6">
            <label class="form-label">End Date/Time</label>
            <input 
              type="datetime-local" 
              name="end_date" 
              class="form-control"
              value="<?php echo htmlspecialchars(toDateTimeLocal($editingEvent['end_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            />
          </div>

          <div class="col-md-6">
            <label class="form-label">Recurrence</label>
            <select name="recurring_interval" class="form-select">
              <?php $interval = $editingEvent['recurring_interval'] ?? 'none'; ?>
              <option value="none"    <?php if($interval==='none') echo 'selected';?>>None</option>
              <option value="daily"   <?php if($interval==='daily') echo 'selected';?>>Daily</option>
              <option value="weekly"  <?php if($interval==='weekly') echo 'selected';?>>Weekly</option>
              <option value="monthly" <?php if($interval==='monthly') echo 'selected';?>>Monthly</option>
              <option value="yearly"  <?php if($interval==='yearly') echo 'selected';?>>Yearly</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Recurrence End</label>
            <input 
              type="datetime-local" 
              name="recurring_end_date"
              class="form-control"
              value="<?php echo htmlspecialchars(toDateTimeLocal($editingEvent['recurring_end_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            />
          </div>

          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control"
            ><?php echo htmlspecialchars($editingEvent['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>

          <div class="col-12">
            <button type="submit" class="btn btn-success">Save Event</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ============== MANAGE CATEGORIES MODAL (unchanged) ============== -->
<div class="modal fade" id="manageCategories" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Manage Categories</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- List categories, add new, etc. -->
        <?php if(!empty($categories)):?>
          <table class="table table-bordered">
            <thead>
              <tr>
                <th>ID</th><th>Name</th><th>Color</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($categories as $cat):?>
              <tr>
                <td><?php echo htmlspecialchars($cat['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($cat['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <span style="display:inline-block;width:20px;height:20px;background:<?php echo htmlspecialchars($cat['color'] ?? '#fff', ENT_QUOTES, 'UTF-8'); ?>;"></span>
                  <?php echo htmlspecialchars($cat['color'] ?? '#fff', ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td style="width:100px;">
                  <a href="?action=delete_category&amp;cat_id=<?php echo urlencode((string)($cat['id'] ?? '')); ?>"
                     class="btn btn-sm btn-danger"
                     onclick="return confirm('Delete category <?php echo htmlspecialchars($cat['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>?');">
                     Delete
                  </a>
                </td>
              </tr>
            <?php endforeach;?>
            </tbody>
          </table>
        <?php else:?>
          <p>No categories yet.</p>
        <?php endif;?>

        <hr>
        <h6>Add Category</h6>
        <form class="row g-3" method="POST" action="?action=add_category">
          <div class="col-md-6">
            <input 
              type="text" 
              name="cat_name" 
              class="form-control" 
              placeholder="Category Name" 
              required
            />
          </div>
          <div class="col-md-4">
            <input 
              type="color" 
              name="cat_color" 
              class="form-control form-control-color" 
              value="#ffffff" 
              title="Pick a color"
            />
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Add</button>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button 
          type="button" 
          class="btn btn-secondary" 
          data-bs-dismiss="modal">
          Close
        </button>
      </div>
    </div>
  </div>
</div>

<script 
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>
<script>
  // If we have ?edit_event, show the modal automatically on page load.
  <?php if ($editingEventId || $openNewEventModal): ?>
    document.addEventListener("DOMContentLoaded", function() {
      var myModal = new bootstrap.Modal(document.getElementById('manageEventModal'));
      myModal.show();
    });
  <?php endif; ?>
</script>
</body>
</html>
