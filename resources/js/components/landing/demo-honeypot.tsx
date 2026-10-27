/**
 * A field people never see nor reach with the keyboard: only a bot fills it in,
 * and the server then answers like a success without storing anything.
 */
export default function DemoHoneypot() {
    return (
        <div
            aria-hidden
            className="absolute -left-[9999px] size-px overflow-hidden"
        >
            <label htmlFor="demo-website">Sitio web</label>
            <input
                id="demo-website"
                type="text"
                name="website"
                tabIndex={-1}
                autoComplete="off"
                defaultValue=""
            />
        </div>
    );
}
