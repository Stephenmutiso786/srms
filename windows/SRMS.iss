; SRMS Windows installer

#define AppName "SRMS"
#define AppVersion "1.0.0"
#define AppPublisher "SRMS"
#define AppURL "https://github.com/Stephenmutiso786/srms"

[Setup]
AppId={{B40C4A5B-3F8B-4A7E-A5F7-6A0A0D0A3C11}}
AppName={#AppName}
AppVersion={#AppVersion}
AppPublisher={#AppPublisher}
AppPublisherURL={#AppURL}
DefaultDirName={autopf}\SRMS
DefaultGroupName=SRMS
OutputBaseFilename=SRMS-Setup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern

[Tasks]
Name: "desktopicon"; Description: "Create a desktop shortcut"; GroupDescription: "Shortcuts:"; Flags: unchecked

[Files]
Source: "..\srms\script\*"; DestDir: "{app}\script"; Flags: recursesubdirs createallsubdirs ignoreversion
Source: "..\srms\database\srms_mysql_schema_clean.sql"; DestDir: "{app}\installer"; Flags: ignoreversion
Source: "runtime\Start-SRMS.bat"; DestDir: "{app}"; Flags: ignoreversion
Source: "runtime\bootstrap.ps1"; DestDir: "{app}\runtime"; Flags: ignoreversion
Source: "runtime\Backup-SRMS.ps1"; DestDir: "{app}\runtime"; Flags: ignoreversion
Source: "runtime\Backup-SRMS.bat"; DestDir: "{app}"; Flags: ignoreversion

[Icons]
Name: "{group}\SRMS"; Filename: "{app}\Start-SRMS.bat"; WorkingDir: "{app}"
Name: "{commondesktop}\SRMS"; Filename: "{app}\Start-SRMS.bat"; WorkingDir: "{app}"; Tasks: desktopicon
Name: "{group}\SRMS Backup"; Filename: "{app}\Backup-SRMS.bat"; WorkingDir: "{app}"

[Run]
Filename: "{app}\Start-SRMS.bat"; Flags: nowait postinstall skipifsilent
