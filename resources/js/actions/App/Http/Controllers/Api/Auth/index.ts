import PhoneAuthController from './PhoneAuthController'
import GoogleAuthController from './GoogleAuthController'
const Auth = {
    PhoneAuthController: Object.assign(PhoneAuthController, PhoneAuthController),
GoogleAuthController: Object.assign(GoogleAuthController, GoogleAuthController),
}

export default Auth