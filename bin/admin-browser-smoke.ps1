param([string]$Base = 'http://127.0.0.1/cricket-club/public/')
$email = $env:MCC_BROWSER_TEST_EMAIL
$password = $env:MCC_BROWSER_TEST_PASSWORD
if ([string]::IsNullOrWhiteSpace($email) -or [string]::IsNullOrWhiteSpace($password)) { throw 'Set MCC_BROWSER_TEST_EMAIL and MCC_BROWSER_TEST_PASSWORD.' }
function Token($body) { $m=[regex]::Match($body,'name="csrf_test_name"[^>]*value="([^"]+)"'); if(!$m.Success){throw 'CSRF field missing'}; return [System.Uri]::UnescapeDataString($m.Groups[1].Value) }
function Post($uri,$body,$session) { try { return Invoke-WebRequest -UseBasicParsing $uri -Method Post -Body $body -WebSession $session -MaximumRedirection 0 -ErrorAction Stop } catch { return $_.Exception.Response } }
function AssertStatus($response,[int]$expected,$label) { if([int]$response.StatusCode -ne $expected){throw "$label expected $expected, got $([int]$response.StatusCode)"} }
$s=New-Object Microsoft.PowerShell.Commands.WebRequestSession
$login=Invoke-WebRequest -UseBasicParsing "$Base/auth/login" -WebSession $s
$lp=Post "$Base/auth/login" @{email=$email;password=$password;csrf_test_name=(Token $login.Content)} $s; AssertStatus $lp 303 'login'
$dash=Invoke-WebRequest -UseBasicParsing "$Base/" -WebSession $s; AssertStatus $dash 200 'dashboard'
foreach($path in @('admin','admin/users','admin/roles','admin/permissions')) { AssertStatus (Invoke-WebRequest -UseBasicParsing "$Base/$path" -WebSession $s) 200 $path }
$new=Invoke-WebRequest -UseBasicParsing "$Base/admin/users/new" -WebSession $s
$created=Post "$Base/admin/users" @{email='managed.staff@example.test';display_name='Managed Browser Staff';password='Managed Staff Password!2026';csrf_test_name=(Token $new.Content)} $s; AssertStatus $created 303 'user create'
$userId=[regex]::Match($created.Headers['Location'],'/admin/users/([0-9]+)').Groups[1].Value
$detail=Invoke-WebRequest -UseBasicParsing "$Base/admin/users/$userId" -WebSession $s; AssertStatus $detail 200 'user detail'
$roles=Invoke-WebRequest -UseBasicParsing "$Base/admin/roles" -WebSession $s; $roleId=[regex]::Match($roles.Content,'/admin/roles/([0-9]+)').Groups[1].Value
AssertStatus (Post "$Base/admin/users/$userId/roles" @{role_id=$roleId;assign='1';csrf_test_name=(Token $detail.Content)} $s) 303 'role assign'
$detail=Invoke-WebRequest -UseBasicParsing "$Base/admin/users/$userId" -WebSession $s; $branchId=[regex]::Match($detail.Content,'name="branch_id"[^>]*value="([0-9]+)"').Groups[1].Value
AssertStatus (Post "$Base/admin/users/$userId/branches" @{branch_id=$branchId;assign='1';csrf_test_name=(Token $detail.Content)} $s) 303 'branch assign'
$edit=Invoke-WebRequest -UseBasicParsing "$Base/admin/users/$userId/edit" -WebSession $s
AssertStatus (Post "$Base/admin/users/$userId" @{email='managed.staff.updated@example.test';display_name='Managed Browser Staff Updated';csrf_test_name=(Token $edit.Content)} $s) 303 'user edit'
$detail=Invoke-WebRequest -UseBasicParsing "$Base/admin/users/$userId" -WebSession $s
AssertStatus (Post "$Base/admin/users/$userId/enabled" @{enabled='0';csrf_test_name=(Token $detail.Content)} $s) 303 'disable'
$other=New-Object Microsoft.PowerShell.Commands.WebRequestSession; $ol=Invoke-WebRequest -UseBasicParsing "$Base/auth/login" -WebSession $other; $disabled=Post "$Base/auth/login" @{email='managed.staff.updated@example.test';password='Managed Staff Password!2026';csrf_test_name=(Token $ol.Content)} $other; AssertStatus $disabled 303 'disabled login'; if($disabled.Headers['Location'] -notmatch '/auth/login'){throw 'Disabled account authenticated unexpectedly.'}
$detail=Invoke-WebRequest -UseBasicParsing "$Base/admin/users/$userId" -WebSession $s; AssertStatus (Post "$Base/admin/users/$userId/enabled" @{enabled='1';csrf_test_name=(Token $detail.Content)} $s) 303 'enable'
$roleNew=Invoke-WebRequest -UseBasicParsing "$Base/admin/roles/new" -WebSession $s
$roleCreated=Post "$Base/admin/roles" @{name='Browser Managed Role';csrf_test_name=(Token $roleNew.Content)} $s; AssertStatus $roleCreated 303 'role create'
$roleNewId=[regex]::Match($roleCreated.Headers['Location'],'/admin/roles/([0-9]+)').Groups[1].Value
$roleDetail=Invoke-WebRequest -UseBasicParsing "$Base/admin/roles/$roleId" -WebSession $s
AssertStatus (Post "$Base/admin/roles/$roleId/permissions" @{permission='lead.create';assign='1';csrf_test_name=(Token $roleDetail.Content)} $s) 303 'permission add'
$fresh=New-Object Microsoft.PowerShell.Commands.WebRequestSession; $fl=Invoke-WebRequest -UseBasicParsing "$Base/auth/login" -WebSession $fresh; AssertStatus (Post "$Base/auth/login" @{email=$email;password=$password;csrf_test_name=(Token $fl.Content)} $fresh) 303 'fresh login'; AssertStatus (Invoke-WebRequest -UseBasicParsing "$Base/crm/leads/new" -WebSession $fresh) 200 'lead create allowed'
$roleDetail=Invoke-WebRequest -UseBasicParsing "$Base/admin/roles/$roleId" -WebSession $fresh
AssertStatus (Post "$Base/admin/roles/$roleId/permissions" @{permission='lead.create';assign='0';csrf_test_name=(Token $roleDetail.Content)} $fresh) 303 'permission remove'
$fresh2=New-Object Microsoft.PowerShell.Commands.WebRequestSession; $fl2=Invoke-WebRequest -UseBasicParsing "$Base/auth/login" -WebSession $fresh2; AssertStatus (Post "$Base/auth/login" @{email=$email;password=$password;csrf_test_name=(Token $fl2.Content)} $fresh2) 303 'final login'; try { Invoke-WebRequest -UseBasicParsing "$Base/crm/leads/new" -WebSession $fresh2 -MaximumRedirection 0 -ErrorAction Stop; throw 'lead create remained accessible after permission removal' } catch { if($_.Exception.Response.StatusCode.value__ -ne 403){throw} }
Write-Output 'ADMIN_BROWSER_SMOKE=PASS'
