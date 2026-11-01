/** What a block says when the numeral has no evidence: the inspector reads «Falta», never a blank. */
export default function DossierMissing({ children }: { children: string }) {
    return (
        <p className="text-[15px] leading-[22px] font-medium">
            Falta: <span className="font-normal">{children}</span>
        </p>
    );
}
