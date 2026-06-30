async function main() {
  const BASE = 'http://127.0.0.1:8080';
  
  // 1. Get captcha
  const captchaRes = await fetch(BASE + '/admin/auth/captcha');
  const captchaData = await captchaRes.json();
  console.log('Captcha:', JSON.stringify(captchaData));
  
  // 2. Login
  const loginRes = await fetch(BASE + '/admin/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      username: 'admin',
      password: 'admin123',
      captcha: captchaData.data.captcha_code,
      captcha_token: captchaData.data.captcha_token
    })
  });
  const loginData = await loginRes.json();
  console.log('Login:', JSON.stringify(loginData, null, 2));
  
  if (!loginData.data || !loginData.data.access_token) {
    console.log('Login failed');
    return;
  }
  
  const accessToken = loginData.data.access_token;
  
  // 3. Profile with Authorization header
  const profileRes = await fetch(BASE + '/admin/auth/profile', {
    headers: {
      'Authorization': 'Bearer ' + accessToken,
      'Content-Type': 'application/json'
    }
  });
  const profileData = await profileRes.json();
  console.log('Profile:', JSON.stringify(profileData, null, 2));
}

main().catch(e => console.error(e));
