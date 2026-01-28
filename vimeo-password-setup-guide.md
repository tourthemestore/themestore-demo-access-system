# Vimeo Password Protection Setup Guide

## Current Configuration
- **Video ID:** 1158330074
- **Password in config.php:** info!@#123

## Steps to Enable Password Protection

### 1. Configure Vimeo Video Settings

1. Go to your Vimeo video: https://vimeo.com/manage/videos/1158330074
2. Click **Settings** (gear icon) or go to **Privacy** tab
3. Under **Privacy**, select **"Password (Only people with the password can view)"**
4. Enter the password: `info!@#123` (must match exactly what's in config.php)
5. **For Embed Settings (if available):**
   - Click on **Settings** → Look for **"Embed"** or **"Sharing"** section
   - OR go to **Settings** → **Privacy** → Scroll down to find embed options
   - OR click the **"..."** (three dots) menu on the video page → **Settings** → **Embed**
   - Make sure **"Allow embedding"** is enabled
   - If you see options for buttons, disable them (these may not be available on all Vimeo plans)
6. Click **Save**

**Note:** If you can't find embed button settings, don't worry! The code now uses URL parameters and CSS to hide buttons automatically. The URL parameters (`like=0&share=0&watchlater=0`) should hide most buttons.

### 2. Verify Password Protection

After saving:
- The video should show a lock icon in Vimeo
- When embedded, it should automatically prompt for password
- The password prompt appears in the video player itself

### 3. Troubleshooting

**If password prompt is NOT showing:**
1. Verify the video privacy is set to "Password" (not "Only me" or "Vimeo members")
2. Check that the password in Vimeo matches exactly with `config.php` (case-sensitive)
3. Clear browser cache and reload the demo link
4. Try accessing the video directly in Vimeo to confirm password protection is active

**If like/share/watch later buttons are still showing:**
1. The code now includes automatic button hiding via:
   - URL parameters (`like=0&share=0&watchlater=0`)
   - CSS injection (attempts to hide buttons in iframe)
2. Some buttons may still appear if:
   - Your Vimeo account type doesn't support hiding them
   - Vimeo has updated their player interface
3. **Workaround:** If buttons persist, you may need:
   - Vimeo Pro or Business plan for full control
   - OR use a custom video player solution
4. Clear browser cache and reload the page

### 4. Testing

1. Request a new OTP for a test lead
2. Check the email - it should contain both OTP and video password
3. Click the demo link
4. The video player should show a password prompt
5. Enter the password from the email
6. Video should unlock and play

## Notes

- The password is sent in the OTP email to authorized users only
- Even if someone gets the demo link, they still need the password
- Password is case-sensitive and must match exactly between Vimeo and config.php
- Changes in Vimeo settings may take a few minutes to apply
