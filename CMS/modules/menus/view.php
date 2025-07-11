<!-- File: view.php -->
                <div class="content-section" id="menus">
                    <div class="table-card">
                        <div class="table-header">
                            <div class="table-title">Menus</div>
                            <div class="table-actions">
                                <button class="btn btn-primary" id="newMenuBtn">+ New Menu</button>
                            </div>
                        </div>
                        <table class="data-table" id="menusTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Items</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <div class="form-card" id="menuFormCard" style="margin-top:20px; display:none;">
                        <h3 id="menuFormTitle" style="margin-bottom:15px;">Add Menu</h3>
                        <form id="menuForm">
                            <input type="hidden" name="id" id="menuId">
                            <div class="form-group">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-input" name="name" id="menuName" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Items</label>
                                <ul id="menuItems" class="menu-list"></ul>
                                <button type="button" class="btn btn-secondary" id="addMenuItem" style="margin-top:5px;">+ Add Item</button>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <button type="submit" class="btn btn-primary">Save Menu</button>
                                <button type="button" class="btn btn-secondary" id="cancelMenuEdit">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

