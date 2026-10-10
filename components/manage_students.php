<?php
require_once("../public/index.php");

require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
?>


<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h3>Manage Users</h3>
    </div>

    <!-- Message Alert Area -->
    <div id="message"></div>

    <!-- Create User Form -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">Create New User</div>
        <div class="card-body">
            <form hx-post="<?= HANDLERS_URL . 'createGuardianProcess.php'; ?>"
                  hx-target="#message"
                  hx-swap="innerHTML"
                  hx-indicator="#global-spinner"
                  hx-on::after-request="if(event.detail.successful) this.reset()">


                <div class="row mt-2">
                    <div class="col-4">
                        <input type="text" name="fname" class="form-control" placeholder="First name" required>
                    </div>
                    <div class="col-4">
                        <input type="text" name="lname" class="form-control" placeholder="Last name" required>
                    </div>
                    <div class="col-4">
                        <input type="text" name="mname" class="form-control" placeholder="Middle name: (Optional)">
                    </div>
                </div>
                
                <div class="row mt-2">
                    <div class="col-4">
                        <input type="email" name="email" class="form-control" placeholder="Email: (Optional)" >
                    </div>
                    <div class="col-4">
                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="123-456-7890: (Optional)" minlength="11">
                    </div>
                </div>



                <button type="submit" class="btn btn-primary mt-3">Create User</button>
            </form>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <!-- reeal-time search with 400ms delay -->
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Search username or email..."
                           hx-get="<?= HANDLERS_URL . 'readGuardians.php'; ?>" 
                           hx-target="#user-table-container" 
                           hx-trigger="keyup changed delay:400ms, search"
                           hx-include="[name='role']"
                           hx-indicator="#global-spinner">
                </div>
                <div class="col-md-4">
                    <!-- filter by role -->
                    <select name="role" 
                            class="form-select"
                            hx-get="<?= HANDLERS_URL . 'readUsers.php'; ?>" 
                            hx-target="#user-table-container" 
                            hx-trigger="change"
                            hx-include="[name='search']"
                            hx-indicator="#global-spinner">
                        <option value="">All Roles</option>
                        <option value="1">Admin</option>
                        <option value="2">User</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- table container (Loads automatically on page open & on 'reloadUsers' event) -->
    <div id="user-table-container" 
         hx-get="<?= HANDLERS_URL . 'readGuardians.php'; ?>" 
         hx-trigger="load, reloadUsers from:body"
         hx-indicator="#global-spinner">
    </div>
</div>