async function main() {
  const BASE = 'http://127.0.0.1:8080';
  
  // 1. Get captcha
  const captchaRes = await fetch(BASE + '/admin/auth/captcha');
  const captchaData = await captchaRes.json();
  console.log('Captcha code:', captchaData.data.captcha_code);
  
  // 2. Login
  const loginBody = JSON.stringify({
    username: 'admin',
    password: 'admin123',
    captcha: captchaData.data.captcha_code,
    captcha_token: captchaData.data.captcha_token
  });
  
  const loginRes = await fetch(BASE + '/admin/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: loginBody
  });
  const loginData = await loginRes.json();
  console.log('Login code:', loginData.code);
  
  if (loginData.code !== 0 || !loginData.data) {
    console.log('Login failed:', loginData.message);
    process.exit(1);
  }
  
  const accToken = loginData.data.access_token;
  console.log('Access token (first 20 chars):', accToken.substring(0, 20) + '...');
  
  // 3. Profile
  const profileRes = await fetch(BASE + '/admin/auth/profile', {
    headers: {
      'Authorization': 'Bearer ' + accToken,
      'Content-Type': 'application/json'
    }
  });
  const profileData = await profileRes.json();
  console.log('Profile code:', profileData.code);
  if (profileData.code === 0 && profileData.data) {
    console.log('User:', profileData.data.user.nickname);
    console.log('Roles:', profileData.data.roles.length);
  } else {
    console.log('Profile failed:', profileData.message);
  }
}

main().catch(e => console.error('Error:', e.message));
