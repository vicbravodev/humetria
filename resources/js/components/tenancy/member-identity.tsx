import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';

/** Who the page is about (board Roles 7c, Persona): their initials, name and email, and the role they hold as a pill. */
export default function MemberIdentity({
    member,
}: {
    member: { name: string; email: string; role_label: string | null };
}) {
    return (
        <div data-test="member-identity" className="flex items-center gap-4">
            <Initials name={member.name} className="size-14 text-lg" />
            <div className="flex min-w-0 flex-col gap-1">
                <span className="ph-mask font-medium">{member.name}</span>
                <span className="flex flex-wrap items-center gap-x-2 gap-y-1 text-meta text-muted-foreground">
                    <span className="ph-mask break-all">{member.email}</span>
                    {member.role_label !== null && (
                        <Badge
                            variant="secondary"
                            className="text-foreground before:hidden"
                        >
                            {member.role_label}
                        </Badge>
                    )}
                </span>
            </div>
        </div>
    );
}
