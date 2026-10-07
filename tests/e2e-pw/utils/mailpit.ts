import { expect, request } from "@playwright/test";
import { env } from "./env";

interface MailpitSummary {
    ID: string;
    Subject: string;
}

export interface ReceivedEmail {
    subject: string;
    text: string;
    html: string;
}

export async function waitForEmail(recipient: string, subject: RegExp): Promise<ReceivedEmail> {
    const api = await request.newContext({ baseURL: `${env.mailpitUrl}/` });

    try {
        let found: MailpitSummary | undefined;

        await expect
            .poll(
                async () => {
                    const response = await api.get("api/v1/search", {
                        params: { query: `to:"${recipient}"` },
                    });

                    const messages: MailpitSummary[] = (await response.json()).messages ?? [];

                    found = messages.find((message) => subject.test(message.Subject));

                    return Boolean(found);
                },
                { message: `no email matching ${subject} reached ${recipient}` },
            )
            .toBe(true);

        const detail = await (await api.get(`api/v1/message/${found!.ID}`)).json();

        return { subject: detail.Subject, text: detail.Text ?? "", html: detail.HTML ?? "" };
    } finally {
        await api.dispose();
    }
}
