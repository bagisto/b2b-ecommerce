import { waitForEmail } from "./mailpit";

export async function readNewMemberPassword(email: string): Promise<string> {
    const message = await waitForEmail(email, /^Your Account Has Been Created$/);

    const password = message.html.match(/Password:\s*(?:<strong>)?(\d{6,8})/)?.[1];

    if (!password) {
        throw new Error(`No password found in the account email sent to ${email}`);
    }

    return password;
}

export async function readInvitationPath(email: string): Promise<string> {
    const message = await waitForEmail(email, /^You Are Invited to Join /);

    const path = message.html.match(/\/customer\/account\/invitations\/[A-Za-z0-9]+/)?.[0];

    if (!path) {
        throw new Error(`No invitation link found in the email sent to ${email}`);
    }

    return path;
}
