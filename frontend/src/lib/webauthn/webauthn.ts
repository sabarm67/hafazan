import { base64urlToBuffer, bufferToBase64url } from './base64url'

/** True when the browser exposes the WebAuthn APIs this app needs at all. */
export function isPasskeySupported(): boolean {
  return typeof window !== 'undefined'
    && typeof window.PublicKeyCredential !== 'undefined'
    && typeof navigator.credentials !== 'undefined'
}

interface ServerCredentialDescriptor {
  type: string
  id: string
  transports?: string[]
}

interface ServerCreationOptions {
  rp: { id: string; name: string }
  user: { id: string; name: string; displayName: string }
  challenge: string
  pubKeyCredParams: Array<{ type: string; alg: number }>
  timeout?: number
  excludeCredentials?: ServerCredentialDescriptor[]
  authenticatorSelection?: Record<string, unknown>
  attestation?: string
}

interface ServerRequestOptions {
  challenge: string
  timeout?: number
  rpId: string
  allowCredentials?: ServerCredentialDescriptor[]
  userVerification?: string
}

function toDescriptor(d: ServerCredentialDescriptor): PublicKeyCredentialDescriptor {
  return {
    type: 'public-key',
    id: base64urlToBuffer(d.id),
    transports: d.transports as AuthenticatorTransport[] | undefined,
  }
}

/** Runs the browser's "create a new passkey" ceremony (registration). */
export async function createPasskeyCredential(options: ServerCreationOptions): Promise<Record<string, unknown>> {
  const publicKey: PublicKeyCredentialCreationOptions = {
    rp: options.rp,
    user: {
      id: base64urlToBuffer(options.user.id),
      name: options.user.name,
      displayName: options.user.displayName,
    },
    challenge: base64urlToBuffer(options.challenge),
    pubKeyCredParams: options.pubKeyCredParams as PublicKeyCredentialParameters[],
    timeout: options.timeout,
    excludeCredentials: options.excludeCredentials?.map(toDescriptor),
    authenticatorSelection: options.authenticatorSelection as AuthenticatorSelectionCriteria | undefined,
    attestation: options.attestation as AttestationConveyancePreference | undefined,
  }

  const credential = await navigator.credentials.create({ publicKey })

  if (!(credential instanceof PublicKeyCredential)) {
    throw new Error('Passkey creation was cancelled or is not supported.')
  }

  const response = credential.response as AuthenticatorAttestationResponse

  return {
    id: credential.id,
    rawId: bufferToBase64url(credential.rawId),
    type: credential.type,
    response: {
      clientDataJSON: bufferToBase64url(response.clientDataJSON),
      attestationObject: bufferToBase64url(response.attestationObject),
    },
  }
}

/** Runs the browser's "use a passkey" ceremony (login/verification). */
export async function getPasskeyAssertion(options: ServerRequestOptions): Promise<Record<string, unknown>> {
  const publicKey: PublicKeyCredentialRequestOptions = {
    challenge: base64urlToBuffer(options.challenge),
    timeout: options.timeout,
    rpId: options.rpId,
    allowCredentials: options.allowCredentials?.map(toDescriptor),
    userVerification: options.userVerification as UserVerificationRequirement | undefined,
  }

  const credential = await navigator.credentials.get({ publicKey })

  if (!(credential instanceof PublicKeyCredential)) {
    throw new Error('Passkey sign-in was cancelled or is not supported.')
  }

  const response = credential.response as AuthenticatorAssertionResponse

  return {
    id: credential.id,
    rawId: bufferToBase64url(credential.rawId),
    type: credential.type,
    response: {
      clientDataJSON: bufferToBase64url(response.clientDataJSON),
      authenticatorData: bufferToBase64url(response.authenticatorData),
      signature: bufferToBase64url(response.signature),
      userHandle: response.userHandle ? bufferToBase64url(response.userHandle) : null,
    },
  }
}
