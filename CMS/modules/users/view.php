                <div class="content-section" id="users">
                    <div class="table-card">
                        <div class="table-header">
                            <div class="table-title">Users</div>
                            <div class="table-actions">
                                <button class="btn btn-primary" id="newUserBtn">+ New User</button>
                            </div>
                        </div>
                        <table class="data-table" id="usersTable">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <div class="form-card" id="userFormCard" style="margin-top:20px; display:none;">
                        <h3 id="userFormTitle" style="margin-bottom:15px;">Add User</h3>
                        <form id="userForm">
                            <input type="hidden" name="id" id="userId">
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-input" name="username" id="username" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-input" name="password" id="password">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Role</label>
                                <select class="form-select" name="role" id="role">
                                    <option value="admin">Admin</option>
                                    <option value="editor">Editor</option>
                                    <option value="viewer">Viewer</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <button type="submit" class="btn btn-primary">Save User</button>
                                <button type="button" class="btn btn-secondary" id="cancelUserEdit">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

