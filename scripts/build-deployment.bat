@echo off
REM ############################################################################
REM Deployment Package Builder for Windows
REM
REM Creates a production-ready ZIP package for cloud hosting (e.g., Hostinger)
REM Windows equivalent of build-deployment.sh
REM
REM Usage:
REM   scripts\build-deployment.bat [output-filename]
REM
REM Example:
REM   scripts\build-deployment.bat chatbot-deploy.zip
REM ############################################################################

setlocal enabledelayedexpansion

REM Configuration
if "%1"=="" (
    set OUTPUT_FILE=chatbot-deploy.zip
) else (
    set OUTPUT_FILE=%1
)

set BUILD_DIR=build\deployment
set TIMESTAMP=%date:~-4,4%%date:~-10,2%%date:~-7,2%_%time:~0,2%%time:~3,2%%time:~6,2%
set TIMESTAMP=%TIMESTAMP: =0%

echo ========================================
echo Building Deployment Package
echo ========================================
echo Output file: %OUTPUT_FILE%
echo Build directory: %BUILD_DIR%
echo.

REM Clean previous build
if exist "%BUILD_DIR%" (
    echo Cleaning previous build directory...
    rmdir /s /q "%BUILD_DIR%"
)

mkdir "%BUILD_DIR%"
echo [OK] Build directory created
echo.

echo ========================================
echo Copying Production Files
echo ========================================

REM Copy root PHP files
for %%f in (*.php) do (
    copy "%%f" "%BUILD_DIR%\" >nul 2>&1
    if !errorlevel! equ 0 echo [OK] Copied: %%f
)

REM Copy CSS and JS files
for %%f in (*.css *.js) do (
    if exist "%%f" (
        copy "%%f" "%BUILD_DIR%\" >nul 2>&1
        if !errorlevel! equ 0 echo [OK] Copied: %%f
    )
)

REM Copy .htaccess and favicon
if exist ".htaccess" copy ".htaccess" "%BUILD_DIR%\" >nul && echo [OK] Copied: .htaccess
if exist "favicon.ico" copy "favicon.ico" "%BUILD_DIR%\" >nul && echo [OK] Copied: favicon.ico

REM Copy directories
xcopy /E /I /Q "api" "%BUILD_DIR%\api\" >nul 2>&1 && echo [OK] Copied: api/
xcopy /E /I /Q "assets" "%BUILD_DIR%\assets\" >nul 2>&1 && echo [OK] Copied: assets/
xcopy /E /I /Q "channels" "%BUILD_DIR%\channels\" >nul 2>&1 && echo [OK] Copied: channels/
xcopy /E /I /Q "db\migrations" "%BUILD_DIR%\db\migrations\" >nul 2>&1 && echo [OK] Copied: db/migrations/
xcopy /E /I /Q "includes" "%BUILD_DIR%\includes\" >nul 2>&1 && echo [OK] Copied: includes/
xcopy /E /I /Q "public" "%BUILD_DIR%\public\" >nul 2>&1 && echo [OK] Copied: public/
xcopy /E /I /Q "webhooks" "%BUILD_DIR%\webhooks\" >nul 2>&1 && echo [OK] Copied: webhooks/

REM Copy composer.json
if exist "composer.json" copy "composer.json" "%BUILD_DIR%\" >nul && echo [OK] Copied: composer.json

REM Remove backup files
del /s /q "%BUILD_DIR%\*.backup" >nul 2>&1
echo [INFO] Removed backup files
echo.

REM Install Composer dependencies
echo ========================================
echo Installing Production Dependencies
echo ========================================

where composer >nul 2>&1
if %errorlevel% equ 0 (
    cd "%BUILD_DIR%"
    echo Running: composer install --no-dev --optimize-autoloader
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    cd ..\..
    echo [OK] Composer dependencies installed
) else (
    echo [WARNING] Composer not found - dependencies must be installed on target server
    echo [INFO] Make sure your hosting runs: composer install --no-dev --optimize-autoloader
)
echo.

REM Create .env template
echo ========================================
echo Creating Environment Template
echo ========================================

if exist ".env.example" (
    copy ".env.example" "%BUILD_DIR%\.env.example" >nul
    echo [OK] Copied .env.example
    echo [WARNING] Remember to configure .env on the server!
) else (
    echo [WARNING] .env.example not found
)
echo.

REM Create deployment info file
echo ========================================
echo Creating Deployment Metadata
echo ========================================

for /f "tokens=*" %%i in ('git rev-parse --abbrev-ref HEAD 2^>nul') do set GIT_BRANCH=%%i
for /f "tokens=*" %%i in ('git rev-parse --short HEAD 2^>nul') do set GIT_COMMIT=%%i

if "%GIT_BRANCH%"=="" set GIT_BRANCH=unknown
if "%GIT_COMMIT%"=="" set GIT_COMMIT=unknown

(
echo Deployment Package Information
echo ================================
echo Generated: %date% %time%
echo Git Branch: %GIT_BRANCH%
echo Git Commit: %GIT_COMMIT%
echo Built By: %USERNAME%
echo Build Host: %COMPUTERNAME%
echo.
echo IMPORTANT: Post-Deployment Steps
echo =================================
echo 1. Upload this package to your hosting service
echo 2. Extract to your web root directory
echo 3. Configure .env file with your credentials:
echo    - Database connection ^(DB_*^)
echo    - OpenAI API key ^(OPENAI_API_KEY^)
echo    - Admin credentials ^(ADMIN_TOKEN^)
echo    - Application URL ^(BASE_URL^)
echo 4. Set proper file permissions ^(755 for directories, 644 for files^)
echo 5. Ensure writable directories:
echo    - logs/ ^(create if not exists^)
echo    - data/ ^(create if not exists^)
echo 6. Run database migrations:
echo    php scripts/run_migrations.php
echo 7. Test the deployment:
echo    - Access your-domain.com/
echo    - Verify admin panel access
echo    - Test chat functionality
echo.
echo Required PHP Extensions:
echo ========================
echo - PHP ^>= 8.0
echo - ext-curl
echo - ext-json
echo - ext-mbstring
echo - ext-pdo
echo - ext-pdo_mysql ^(or pdo_sqlite^)
echo.
echo Security Checklist:
echo ===================
echo [ ] .env file configured and NOT publicly accessible
echo [ ] Admin token is strong and unique
echo [ ] Database credentials are secure
echo [ ] File permissions are correct
echo [ ] HTTPS is enabled
echo [ ] Error display is disabled ^(display_errors = Off^)
echo [ ] logs/ directory is not web-accessible
echo.
echo For issues or questions, refer to: README.md
) > "%BUILD_DIR%\DEPLOYMENT_INFO.txt"

echo [OK] Created DEPLOYMENT_INFO.txt
echo.

REM Create ZIP archive
echo ========================================
echo Creating ZIP Archive
echo ========================================

if exist "%OUTPUT_FILE%" (
    del "%OUTPUT_FILE%"
    echo [INFO] Removed existing %OUTPUT_FILE%
)

REM Check for 7-Zip or PowerShell
where 7z >nul 2>&1
if %errorlevel% equ 0 (
    echo Using 7-Zip...
    7z a -tzip "%OUTPUT_FILE%" ".\%BUILD_DIR%\*" -r >nul
    echo [OK] ZIP archive created with 7-Zip
) else (
    echo Using PowerShell Compress-Archive...
    powershell -Command "Compress-Archive -Path '%BUILD_DIR%\*' -DestinationPath '%OUTPUT_FILE%' -Force"
    echo [OK] ZIP archive created with PowerShell
)

if exist "%OUTPUT_FILE%" (
    for %%A in ("%OUTPUT_FILE%") do set FILE_SIZE=%%~zA
    set /a FILE_SIZE_MB=!FILE_SIZE! / 1048576
    echo [OK] Package created: %OUTPUT_FILE% ^(!FILE_SIZE_MB! MB^)
) else (
    echo [ERROR] Failed to create ZIP archive
    exit /b 1
)
echo.

REM Generate checksum
echo ========================================
echo Generating Checksum
echo ========================================

powershell -Command "$hash = (Get-FileHash -Algorithm SHA256 '%OUTPUT_FILE%').Hash; Write-Output \"$hash  %OUTPUT_FILE%\" | Out-File -Encoding ASCII '%OUTPUT_FILE%.sha256'; Write-Output \"SHA256: $hash\""
echo [OK] Checksum saved to: %OUTPUT_FILE%.sha256
echo.

REM Summary
echo ========================================
echo Build Complete!
echo ========================================
echo.
echo Deployment package ready:
echo   [Package] %OUTPUT_FILE%
echo   [Checksum] %OUTPUT_FILE%.sha256
echo.
echo Next steps:
echo   1. Review DEPLOYMENT_INFO.txt inside the ZIP
echo   2. Upload to your hosting service ^(e.g., Hostinger^)
echo   3. Configure .env with your production credentials
echo   4. Run database migrations
echo   5. Test the deployment
echo.
echo [WARNING] Remember: NEVER commit .env files with actual credentials!
echo.

endlocal
