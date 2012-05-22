SET mypath=%~dp0
SET mypath=%mypath:~0,-1%

icacls "%mypath%" /grant BUILTIN\IIS_IUSRS:(OI)(CI)(M) /t
